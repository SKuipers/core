<?php

namespace Gibbon\Data;

/**
 * Background Processor
 *
 * @version v18
 * @since   v18
 */
class BackgroundProcess
{
    protected $directoryPath;

    protected $processFilename = 'Processing.txt';
    protected $outputFilename = 'Output.txt';

    /**
     * Create a background processor, outputting the process file to a specific folder
     *
     * @param  string  $directoryPath
     */
    public function __construct($directoryPath)
    {
        $this->directoryPath = rtrim($directoryPath, '/');

        if (!is_dir($this->directoryPath)) {
            mkdir($this->directoryPath, 0755);
        }
    }

    /**
     * Starts a new background process with a given name (used to check it's status). Pass args as an array.
     *
     * @param   string  $processName
     * @param   string  $phpFile
     * @param   array   $args
     * @return  bool
     */
    public function startProcess($processName, $phpFile, array $args = array()) : bool
    {
        if (empty($processName) || empty($phpFile) || !is_array($args)) {
            throw new \InvalidArgumentException();
        }

        if (!file_exists($phpFile)) {
            throw new \InvalidArgumentException('File not found: '.$phpFile);
        }

        // if ($this->isProcessRunning($processName)) {
        //     $this->stopProcess($processName);
        // }

        try {
            $argsEsc = array_map(function($arg) { return escapeshellarg($arg); }, $args);
            $command = PHP_BINDIR.'/php '.escapeshellarg($phpFile).' '.implode(' ', $argsEsc);

            exec(sprintf("%s > %s 2>&1 & echo $! > %s",
                    $command,
                    $this->getOutputFile($processName),
                    $this->getProcessFile($processName)
                )
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Kills the process by name and clear the files.
     *
     * @param   string  $processName
     * @return  bool
     */
    public function stopProcess($processName) : bool
    {
        $pID = $this->getPID($processName);
        $deleted = $this->deleteProcessFile($processName);
        exec('kill -9 '.$pID);
        return $deleted;
    }

    /**
     * Check if a process is running by polling the pID, as well as checking the processFile existance.
     *
     * @param   string  $processName
     * @return  bool
     */
    public function isProcessRunning($processName) : bool
    {
        $pID = $this->getPID($processName);

        if (empty($pID)) return false;

        $checkProcess = exec('ps '.$pID);
        if (stripos($checkProcess, $pID) !== false) {
            return true;
        } else {
            $this->deleteProcessFile($processName);
            return false;
        }
    }

    /**
     * Return the system pID of the process by name
     *
     * @param   string  $processName
     * @return  string
     */
    public function getPID($processName) : string
    {
        if (file_exists($this->getProcessFile($processName))) {
            $pid = file_get_contents($this->getProcessFile($processName));
            return preg_replace('/[^0-9]/', '', $pid);
        } else {
            return '';
        }
    }

    /**
     * Get the absolute file path of the Output File
     *
     * @param   string  $processName
     * @return  string
     */
    public function getOutputFile($processName) : string
    {
        $processName = $this->sanitizeProcessName($processName);

        return $this->directoryPath.'/'.$processName.$this->outputFilename;
    }

    /**
     * Get the absolute file path of the Process File (system pID)
     *
     * @param   string  $processName
     * @return  string
     */
    public function getProcessFile($processName) : string
    {
        $processName = $this->sanitizeProcessName($processName);

        return $this->directoryPath.'/'.$processName.$this->processFilename;
    }

    /**
     * Delete the process file (when complete or cancelled)
     *
     * @param   string  $processName
     * @return  bool
     */
    public function deleteProcessFile($processName) : bool
    {
        return unlink($this->getProcessFile($processName));
    }

    /**
     * Remove special characters and limit the process name length.
     *
     * @param   string  $processName
     * @return  string
     */
    protected function sanitizeProcessName($processName) : string
    {
        $processName = preg_replace('/[^a-zA-Z0-9]/', '', $processName);
        return substr($processName, 0, 20);
    }
}
