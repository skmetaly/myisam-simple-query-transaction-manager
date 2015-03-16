<?php

namespace PaulB;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TransactionRunner
 * @package PaulB
 */
class TransactionRunner extends Command
{

    /**
     * @var output
     */
    protected $_output;

    /**
     * GETTER
     *
     * @return mixed
     */
    public function getOutput()
    {
        return $this->_output;
    }

    /**
     * SETTER
     *
     * @param OutputInterface $output
     */
    protected function setOutput(OutputInterface $output)
    {
        $this->_output = $output;
    }

    /**
     * @param $message
     */
    protected function error($message)
    {
        $this->_output->writeln('<error>'.$message.'</error>');
    }

    /**
     * @param $message
     */
    protected function success($message)
    {
        $this->_output->writeln('<fg=black;bg=cyan>'.$message.'</fg=black;bg=cyan>');
    }
}