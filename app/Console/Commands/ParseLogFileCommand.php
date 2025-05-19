<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ParseLogFileCommand extends Command
{
    protected $signature = 'log:monitoring:parse-file {file}';
    protected $description = 'Parse a CSV log file';

    public function handle(): int
    {
        $filePath = (string) $this->argument('file');
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("The file '$filePath' does not exist or is not readable.");
            return CommandAlias::FAILURE;
        }

        try {
            $this->parseCsvFile($filePath);
            $this->info('CSV file parsed successfully.');
        } catch (Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            return CommandAlias::FAILURE;
        }

        return CommandAlias::SUCCESS;
    }

    /** @throws Exception */
    private function parseCsvFile(string $filePath): void
    {
        if (($handle = fopen($filePath, 'r')) === false) {
            throw new \RuntimeException('Failed to open the file.');
        }

        $logEntries = [];
        while (($csvRow = fgetcsv($handle)) !== false) {
            $eventType = trim($csvRow[2]);
            $timestampKey = match ($eventType) {
                'END' => 'ended_at',
                'START' => 'started_at',
                default => throw new Exception('Invalid event type'),
            };

            $processId = $csvRow[3];
            $logEntries[$processId]['pid'] = $processId;
            $logEntries[$processId]['description'] = $csvRow[1];
            $logEntries[$processId][$timestampKey] = $csvRow[0];
        }
        fclose($handle);

        $jobs = collect($logEntries)
            ->map(function ($process) {
                if (!isset($process['ended_at'])) {
                    $process['stillProcessing'] = true;
                    $process['total_time'] = 0;

                    return $process;
                }

                $process['stillProcessing'] = false;
                $process['total_time'] = Carbon::createFromDate($process['started_at'])
                    ->diffInMinutes(Carbon::createFromDate($process['ended_at']));
                return $process;
            });

        $this->produceReportFor($jobs);
    }

    private function produceReportFor(Collection $jobs): void
    {
        $jobs->each(function (array $job) {
            $processId = $job['pid'];
            if ($job['stillProcessing']) {
                $diffInMinutes = Carbon::createFromDate($job['started_at'])->diffInMinutes(now());
                $this->info("Process $processId still running for $diffInMinutes minutes.");
                $this->checkProcessingTime($processId, $diffInMinutes);
            }

            $this->checkProcessingTime($processId, $job['total_time']);
        });
    }

    private function checkProcessingTime(int $processId, float $diffInMinutes): void
    {
        if ($diffInMinutes > 10) {
            $this->error("Process $processId has been running for too long ($diffInMinutes minutes)!");
        } elseif ($diffInMinutes > 5) {
            $this->warn("Process $processId is taking longer than expected ($diffInMinutes minutes).");
        }
    }
}
