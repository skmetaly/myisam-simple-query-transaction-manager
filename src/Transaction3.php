<?php

namespace PaulB;

use PaulB\models\Transaction;
use PaulB\services\TransactionManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Transaction3
 * @package PaulB
 */
class Transaction3 extends TransactionRunner
{

    /**
     * @var
     */
    protected $_transactionManager;

    /**
     *
     */
    public function configure()
    {
        $this->setName('t3')->setDescription('Transaction 3 runner');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setOutput($output);

        $this->setTransactionManager();

        $this->runDemoTransaction();
    }

    /**
     *
     */
    private function setTransactionManager()
    {
        global $transactionManager;

        $transactionManager = new TransactionManager($this->getOutput());

        $transactionManager->init();
    }

    /**
     *
     */
    public function runDemoTransaction()
    {
        global $transactionManager;

        try {

            $transaction = $transactionManager->getTransaction();

            $transaction->beginTransaction();

            $results = $transaction->runQuery('SELECT * FROM second_records', 'db1');

            $this->success('Read : ' . count($results) . ' records');

            $seconds = 15;

            $this->success('Transaction executed successfully');

            $this->error('Sleeping for ' . $seconds . ' seconds then rollback');

            sleep($seconds);

            $transaction->commit();

            $this->success('Transaction ended successfully');

        } catch (\Exception $e) {
            $this->error('Transaction ended in an error');
        }

    }
}