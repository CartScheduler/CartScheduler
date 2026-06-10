<?php

namespace App\Http\Controllers;

use App\Lib\FilterAnsiEscapeSequencesStreamedOutput;
use App\Settings\GeneralSettings;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Response;
use Log;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRunSoftwareUpdateController extends Controller
{
    public function __construct(
        private readonly GeneralSettings $settings,
    ) {
    }

    public function __invoke()
    {
        // Ensure any existing output buffers are cleared
        if (ob_get_level()) {
            ob_clean();
        }

        if (config('app.env') === 'local') {
            // @codeCoverageIgnoreStart
            return $this->localResponse();
            // @codeCoverageIgnoreEnd
        }

        $available = $this->settings->availableVersion;
        // Create stream
        $stream = fopen('php://output', 'wb');

        $output = App::make(
            abstract: FilterAnsiEscapeSequencesStreamedOutput::class,
            parameters: [
                'stream'    => $stream,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                'decorated' => true,
            ]
        );

        return Response::stream(
            callback: $this->runUpdateCommand($output, $available, $stream),
            headers: [
                'Content-Type'      => 'text/plain',
                'X-Accel-Buffering' => 'no',
                'Cache-Control'     => 'no-cache',
            ]);
    }

    // @codeCoverageIgnoreStart
    public function localResponse(): StreamedResponse
    {
        $this->settings->currentVersion = $this->settings->availableVersion;
        $this->settings->save();

        return Response::stream(function () {
            $output = App::make(
                abstract: StreamOutput::class,
                parameters: ['stream' => fopen('php://output', 'wb')]
            );
            $output->writeln('NOTICE! Cannot run software update in local environment.');
            $output->writeln('Faking Update to version ' . $this->settings->availableVersion . '.');
        });
    }
    // @codeCoverageIgnoreEnd

    protected function runUpdateCommand(StreamOutput $output, string $available, mixed $stream): callable
    {
        return static function () use ($available, $output, $stream) {
            try {

                $output->writeln("Running Software Update... (Version: $available).");
                $output->writeln('NOTE: THIS MAY TAKE A WHILE...');

                $params   = ['--force' => true, '--install-version' => $available];
                $exitCode = Artisan::call('streamline:run-update', $params, $output);

                if ($exitCode !== 0) {
                    throw new RuntimeException('Command failed');
                }

                fclose($stream);
            } catch (Exception $e) {

                // Log the error
                Log::error('Command streaming failed: ' . $e->getMessage());

                // Output error message to stream
                echo 'Error: ' . $e->getMessage();

                // Ensure stream is closed
                if (isset($stream) && is_resource($stream)) {
                    fclose($stream);
                }
            }
        };
    }
}
