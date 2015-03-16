<?php
namespace PaulB;

use PaulB\services\TransactionManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Transaction1
 * A demo for the transaction manager
 * @package PaulB
 */
class Transaction1 extends TransactionRunner
{

    /**
     * @var
     */
    protected $_transactionManager;

    /**
     *  Sets the name and description
     */
    public function configure()
    {
        $this->setName('t1')->setDescription('Transaction runner');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setOutput($output);

        $this->setTransactionManager();

        $this->runDemoTransaction();
    }

    /**
     *  Setter
     */
    private function setTransactionManager()
    {
        global $transactionManager;

        $transactionManager = new TransactionManager($this->getOutput());

        $transactionManager->init();
    }

    /**
     *  Runs the demo of transaction manager
     */
    public function runDemoTransaction()
    {
        global $transactionManager;

        try {

            //  Get a transaction instance from the manager
            $transaction = $transactionManager->getTransaction();

            $transaction->setOutput($this->getOutput());

            $transaction->beginTransaction();

            $transaction->runQuery('INSERT INTO records(field1,field2,field3) VALUES("'.rand(0,100).'","'.rand(0,100).'","'.rand(0,100).'")', 'db1');

            $transaction->runQuery('UPDATE records SET field4="' . rand(0, 100) . '", field3="' . rand(0,
                    100) . '" WHERE id=3', 'db1');

            $results = $transaction->runQuery('SELECT * FROM records', 'db1');

            $results = $transaction->runQuery('SELECT * FROM second_records', 'db1');

            $transaction->runQuery('UPDATE records_2_second SET field1="' . rand(0, 100) . '", field2="' . rand(0,
                    100) . '" WHERE id=1', 'db2');

            $transaction->runQuery('INSERT INTO second_records(field1,field2,field3) VALUES("'.rand(0,100).'","'.rand(0,100).'","'.rand(0,100).'")', 'db1');

            $transaction->runQuery('DELETE FROM second_records WHERE id<=3', 'db1');

            $transaction->runQuery('DELETE FROM records WHERE id<=14', 'db1');

            $seconds = 15;

            $this->success('Transaction executed successfully');

            $this->error('Sleeping for ' . $seconds . ' seconds then rollback');

            //  sleep is used in this case to simulate operations delay
            sleep($seconds);

            $transaction->rollback();

            $this->success('Transaction ended successfully');

        } catch (\Exception $e) {
            $this->error('Transaction ended in an error');
        }

    }
}