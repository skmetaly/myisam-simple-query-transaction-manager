# myisam-simple-query-transaction-manager
Distributed transaction system for MyISAM . This is only a small demo to show how a distributed transaction system can interact when they are ran concurently. 

# Usage

There are 3 exampels of transaction commands 

~~~
src/Transaction1.php
src/Transaction2.php
src/Transaction3.php
~~~

In order to run the the examples :

 * Import the databases in db dump
 * Make ./transaction executable ( chmod +x ./transaction ) 
 * run ./transaction t1
 * run concurently ./transaction t2
 * or run ./transaction t3 
 
 The sources of the files represents all simple queries that are currently supported

