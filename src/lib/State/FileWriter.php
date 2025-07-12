<?php

namespace Dapr\State;

/**
 * Class FileWriter
 * @package Dapr\State
 */
class FileWriter
{

    /**
     * @param string $filename The filename to write to
     * @param string $contents The contents of the file to write
     *
     * @return void
     */
    public static function write(string $filename, string $contents)
    {
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), recursive: true);
        }
        $handle = fopen($filename, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open ' . $filename . ' for writing!');
        }
        $content_length = strlen($contents);
        $write_result = fwrite($handle, $contents, $content_length);
        if ($write_result !== $content_length) {
            throw new \RuntimeException('Failed to write all content to ' . $filename);
        }
        $flush_result = fflush($handle);
        if ($flush_result === false) {
            throw new \RuntimeException('Failed to flush ' . $filename . ' to disk.');
        }
        $close_result = fclose($handle);
        unset($handle);
        if ($close_result === false) {
            throw new \RuntimeException('Failed to close ' . $filename);
        }
    }
}
