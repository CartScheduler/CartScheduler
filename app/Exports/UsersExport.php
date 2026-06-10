<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/*******************************************************************************************************************
 * @see \App\Imports\UsersImport CHANGES IN THIS FILE NEED HERE MAY NEED TO BE REFLECTED HERE
 *******************************************************************************************************************/
class UsersExport extends DefaultValueBinder implements
    FromQuery,
    ShouldAutoSize,
    WithHeadings,
    WithCustomValueBinder,
    WithDefaultStyles,
    WithMapping,
    WithStyles
{
    private bool $excludeData = false;

    public function excludeData(): static
    {
        $this->excludeData = true;
        return $this;
    }

    /**
     * @noinspection PhpUnused
     * @codeCoverageIgnore
     */
    public function includeData(): static
    {
        $this->excludeData = false;
        return $this;
    }

    public function query(): Builder
    {
        return User::query()
            ->when($this->excludeData, fn(Builder $query) => $query->whereRaw('true = false'))
            ->with(['spouse']);
    }

    /**
     * @param User $row
     */
    public function map($row): array
    {
        return [
            $row->name,
            $row->email,
            $row->mobile_phone,
            $row->gender,
            $row->year_of_birth,
            $row->appointment,
            $row->serving_as,
            $row->marital_status,
            $row->spouse?->email,
            $row->spouse_id,
            $row->responsible_brother,
            $row->is_unrestricted,
        ];
    }

    public function headings(): array
    {
        return [
            [
                'PLACEHOLDER OTHERWISE THIS DOES NOT WORK'
            ],
            [
                'NAME',
                'EMAIL',
                'MOBILE PHONE',
                'GENDER',
                'YEAR OF BIRTH',
                'APPOINTMENT',
                'SERVING AS',
                'MARITAL STATUS',
                'SPOUSE EMAIL',
                'SPOUSE ID',
                'RESPONSIBLE BROTHER',
                'IS UNRESTRICTED',
            ]];
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function styles(Worksheet $sheet): void
    {
        $sheet->getRowDimension(1)->setRowHeight(280);
        $style = $sheet->getStyle('A1');
        $style->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(Color::COLOR_YELLOW);
        $style->getAlignment()->setWrapText(true);
        $this->applyInstructions($sheet);
        $sheet->mergeCells('A1:L1');

        $sheet->getStyle('C')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function applyInstructions(Worksheet $sheet): void
    {
        $richText = new RichText();

        $this->doText($richText, 'DO NOT DELETE THIS ROW! RETAIN THE HEADING ROW!', true, true);
        $this->doText($richText, 'Instructions: Starting on Row 3, fill in the relevant fields. Do not delete rows 1 and 2.', true, true);
        $this->doText($richText, "For SPOUSE EMAIL and SPOUSE ID, these are mutually exclusive. If using them (they're not mandatory), use one or the other but not both. If both are used, the ID will take precendence and the email will be ignored.", true, true);
        $this->doText($richText, $this->newLine());
        $this->doText($richText, 'NAME: ', false, true);
        $this->doText($richText, 'Full Name', true);
        $this->doText($richText, 'EMAIL: ', false, true);
        $this->doText($richText, 'Properly formatted email address. ', false, true);
        $this->doText($richText, 'NOTE, IF AN EMAIL ADDRESS ALREADY EXISTS FOR A USER IN THE SYSTEM, IT WILL UPDATE THAT USER RECORD.', true, true, true);
        $this->doText($richText, 'MOBILE PHONE: ', false, true);
        $this->doText($richText, 'In the format 04XXXXXXXX (spaces can be used)', true);
        $this->doText($richText, 'GENDER: ', false, true);
        $this->doText($richText, 'male, female, m or f', true);
        $this->doText($richText, 'YEAR OF BIRTH: ', false, true);
        $this->doText($richText, 'Optional. 4 digit year. Eg: 1985', true);
        $this->doText($richText, 'APPOINTMENT: ', false, true);
        $this->doText($richText, 'Optional. Allowed values only: elder, ministerial servant', true);
        $this->doText($richText, 'SERVING AS: ', false, true);
        $this->doText($richText, 'Optional. Allowed values only: field missionary, special pioneer, bethel family member, regular pioneer, publisher', true);
        $this->doText($richText, 'MARITAL STATUS: ', false, true);
        $this->doText($richText, 'Optional. Allowed values only: single, married, separated, divorced, widowed', true);
        $this->doText($richText, 'SPOUSE EMAIL: ', false, true);
        $this->doText($richText, 'Optional. Used to link spouses together. If a matching email is found, it will attach the users', true);
        $this->doText($richText, 'SPOUSE ID: ', false, true);
        $this->doText($richText, 'Optional. Used to link a user that already exists in the system to this user', true);
        $this->doText($richText, 'RESPONSIBLE BROTHER: ', false, true);
        $this->doText($richText, 'Indicates in the system that a user (brother) has been trained to oversee a shift. Allowed values only. TRUE, FALSE.', true);
        $this->doText($richText, 'IS UNRESTRICTED: ', false, true);
        $this->doText($richText, "TRUE is the default. If set to false (i.e. indicating they're a 'restricted' user), the volunteer cannot self-roster and they cannot see any shifts other than those they've been rostered onto. Allowed values only. TRUE, FALSE.", true);

        $sheet->getCell('A1')->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getCell('A1')->getStyle()->getFont()->setSize(12);
        $sheet->getCell('A1')->setValue($richText);
    }

    protected function doText(RichText $richText, string $text, $newLine = false, $bold = false, $underline = false): void
    {
        if ($bold || $underline) {
            $formatted = $richText->createTextRun($text . $this->newLine($newLine));
            if ($bold) {
                $formatted->getFont()?->setBold(true);
            }
            if ($underline) {
                $formatted->getFont()?->setUnderline(true);
            }
        } else {
            $richText->createText($text . $this->newLine($newLine));
        }
    }

    protected function newLine($do = true): string
    {
        return $do ? "\r" : '';
    }

    public function defaultStyles(Style $defaultStyle)
    {
        return $defaultStyle->getFont()->setSize(12);
    }
}
