<?php

namespace App\Services\Rap;

use RuntimeException;

class RapPdfExtractor
{
    public function extract(string $pdfPath): string
    {
        if (! is_file($pdfPath) || ! is_readable($pdfPath)) {
            throw new RuntimeException("PDF RAP introuvable ou illisible : {$pdfPath}");
        }

        $command = ['pdftotext', '-layout', $pdfPath, '-'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Impossible de lancer pdftotext.');
        }

        $text = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $text === false || trim($text) === '') {
            throw new RuntimeException('pdftotext a échoué : '.trim((string) $error));
        }

        return $text;
    }
}
