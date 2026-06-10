<?php

namespace App\Imports;

use App\Actions\GetUserValidationUtils;
use App\Enums\Role;
use App\Mail\UserAccountCreated;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

/*******************************************************************************************************************
 * @see \App\Exports\UsersExport CHANGES IN THIS FILE NEED HERE MAY NEED TO BE REFLECTED HERE
 *******************************************************************************************************************/
class UsersImport implements
    /**
     * @uses \Maatwebsite\Excel\Concerns\OnEachRow in place of  {@see \Maatwebsite\Excel\Concerns\ToModel}
     * @link https://docs.laravel-excel.com/3.1/imports/model.html#handling-persistence-on-your-own
     */
    OnEachRow,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure

{
    use SkipsFailures {
        onFailure as attachFailure;
    }

    private int $createCount = 0;
    private int $updateCount = 0;
    private int $failureCount = 0;

    public function __construct(private readonly GetUserValidationUtils $validationUtils)
    {
    }

    public function onRow(Row $row): void
    {
        $rowData = $row->toArray();

        $data = [
            'name'                => $rowData['name'],
            'email'               => $rowData['email'],
            'mobile_phone'        => $rowData['mobile_phone'],
            'gender'              => $rowData['gender'],
            'year_of_birth'       => $this->tidyNullableData($rowData['year_of_birth']),
            'appointment'         => $this->tidyNullableData($rowData['appointment']),
            'serving_as'          => $this->tidyNullableData($rowData['serving_as']),
            'marital_status'      => $this->tidyNullableData($rowData['marital_status']),
            'spouse_id'           => $this->tidyNullableData($rowData['spouse_id']),
            'responsible_brother' => $rowData['responsible_brother'] ?: false,
            'is_unrestricted'     => $rowData['is_unrestricted'] ?? true,
        ];

        $user = User::where('email', '=', $rowData['email'])->first();
        if ($user) {
            $user->update($data);
            ++$this->updateCount;
        } else {
            $data['uuid']     = Str::uuid();
            $data['password'] = null;
            // Create the user quietly (by default, User::create() sends a welcome email. Then, only send the welcome
            // email after the transaction is committed.
            $user             = User::createQuietly($data);
            $user->availability()->create();
            DB::afterCommit(static function () use ($user) {
                User::sendWelcomeEmail($user);
                User::attachSpouse($user);
            });
            ++$this->createCount;
        }
    }

    public function onFailure(Failure ...$failures): void
    {
        ++$this->failureCount;
        $this->attachFailure(...$failures);
    }

    public function prepareForValidation(array $data, int $index): array
    {
        $data['role'] = Role::User->value;

        return app()->make(GetUserValidationUtils::class)->prepare($data);
    }

    public function rules(): array
    {
        $rules = $this->validationUtils->rules();

        $rules['spouse_email'] = ['nullable', 'email'];
        $rules['spouse_id']    = ['nullable', 'exists:users,id'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $data = $validator->getData();
        $key  = key($data);
        $data = current($data);

        $validator->after(
            $this->validationUtils->extraValidation(false, $key, $data)
        );
    }

    private function tidyNullableData(?string $datum): ?string
    {
        return (!$datum || !trim($datum)) ? null : $datum;
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function getCreateCount(): int
    {
        return $this->createCount;
    }

    public function getUpdateCount(): int
    {
        return $this->updateCount;
    }

    /**
     * @throws ValidationException
     */
    public static function importUploadedFiles(UploadedFile $files): self
    {
        /**
         * We're handling transactions manually, so disable LaravelExcel transaction handler
         * @link https://docs.laravel-excel.com/3.1/imports/validation.html#disable-transactions
         */
        Config::set('excel.transactions.handler', 'null');
        try {
            DB::beginTransaction();
            $import = app()->make(__CLASS__);
            Excel::import($import, $files);
            if ($import->failures() && $import->failures()->count()) {
                $failures = $import->failures();
                throw ValidationException::withMessages(
                    $failures->map(
                        fn(Failure $failure) => collect($failure->errors())
                            ->map(fn($error) => __(
                                'Row :row - :message', ['row' => $failure->row(), 'message' => $error])
                            )
                    )->toArray()
                );
            }
            DB::commit();
            return $import;
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}
