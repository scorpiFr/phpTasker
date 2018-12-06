#!/usr/bin/php
<?php

/**
 * Gestionnaire de tache : prend les taches et les fait executer.
 * Il s'arrange pour que les taches en cours en meme temps ne soients pas plus de $maxNbrTasksRunning (10 par defaut).
 *
 * note : ce script doit etre appele par une ligne de commande (ou symfony command), sinon il ne fonctionne pas.
 * @author : camille khalaghi
 *
 */

include_once('lib/Lock.php');
include_once('lib/FineDatabase.php');
include_once('lib/TaskDao.php');
include_once('config/config.php');

function myLog($log) {
    $logFileName = 'log/exec.taskmanager.log';

    // soucis du "\n" final
    $len = strlen($log);
    if ($log[$len - 1] != "\n")
        $log .= "\n";

    // date
    $log = date('c') . ' ' . $log;

    // enregistrement
    file_put_contents($logFileName, $log, FILE_APPEND);

    // retour
    unset($len, $log);
}


// verification du lock
{
    $lock = new Lock($lockFileName);
    if (!$lock->lock()) {
        myLog('TRACE : Trying to open Lock : failed.');
        exit(0);
    }
    myLog('INFO : Trying to open Lock : Accepted !');
}


// initialisations
$logFileName = 'log/exec.taskmanager.log';
set_time_limit(0);


// Traitement des taches

while (true) {
    // connexion sql
    $db = new FineDatabase($databaseHost, $databaseUser, $databasePassword, $databaseName, $databasePort);
    $taskDao = new TaskDao($db);


    // remet a waiting les taches reservees depuis plus d'une heure
    $taskDao->cleanTasks($hostname);

    // prise du nombre de taches a envoyer
    {
        $nbrTasksByStatus = $taskDao->countByStatus($hostname);
        if (empty($nbrTasksByStatus) || empty($nbrTasksByStatus['waiting'])) {
            // pas de taches du tout dans la table.
            unset($nbrTasksByStatus);
            myLog('TRACE : No tasks : waiting 5 sec');
            sleep(5);
            continue;
        }
        $nbrTasksToSend = $maxNbrTasksRunning - $nbrTasksByStatus['started'] - $nbrTasksByStatus['reserved'];
        if ($nbrTasksToSend <= 0) {
            // trop de taches deja lancees
            unset($nbrTasksByStatus);
            myLog('TRACE : Too much tasks running : waiting 5 sec');
            sleep(5);
            continue;
        }
        unset($nbrTasksByStatus);
    }

    // prise des taches
    {
        myLog('TRACE : Picking ' . $nbrTasksToSend . ' tasks .');
        $tasks = $taskDao->getWaitingTasks($nbrTasksToSend, $hostname);
        if (empty($tasks)) {
            unset($tasks, $nbrTasksByStatus);
            myLog('TRACE : No tasks : waiting 5 sec');
            sleep(5);
            continue;
        }
    }

    // envoi des taches
    {
        declare(ticks=1); // active le pcntl
        foreach ($tasks as $task) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                myLog('ERROR : Fork failed');
                die('ERROR : Fork failed \n');
                exit(1);
            } else if (empty($pid)) {
                // processus fils
                $url = $baseUrl . '/process.php?id=' . $task['id'];
                myLog('TRACE : send task : ' . $task['id'] . ' .');
                file_get_contents($url);
                unset($url);
                exit(0);
            } else {
                // processus pere
                usleep(2000); // 0.002 secondes
            }
        }
        $db->close();
        unset($taskDao, $db);
        unset($tasks, $task);
    }
}

?>