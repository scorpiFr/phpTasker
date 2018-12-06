<?php

/**
 * Execute une tache.
 * Le numero de la tache est en parametre get : id
 *
 * Le script est, en fonctionnement normal, appele via une url.
 */

include_once('lib/FineDatabase.php');
include_once('lib/TaskDao.php');
include_once('config/config.php');
$logFileName = 'log/worker.log';


function myLog($log) {
    global $logFileName;

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

// verification des donnees d'entree
if (!isset($_GET['id']) || empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    myLog("ERROR : Not called with id.");
    exit("1");
}

// initialisations & sql
set_time_limit(0);
$db = new FineDatabase($databaseHost, $databaseUser, $databasePassword, $databaseName, $databasePort);
$taskDao = new TaskDao($db);

// prise de la tache
{
    $task = $taskDao->getTask($_GET['id']);
    if (empty($task)) {
        myLog("ERROR : Worked called with a bad task id : '{$_GET['id']}' .");
        exit(1);
    }

    if ($task['status'] == 'started' || $task['status'] == 'finished') {
        myLog("ERROR : {$task['id']} is on bad status : {$task['status']}. Cannot go on with this status.");
        exit(0);
    }

}

// demarrage de la tache
myLog("Trace : starting task : {$task['id']} {$task['appname']} / {$task['taskname']} .");
$taskDao->setStatus($task['id'], 'started');


// essai des routes
$postponeDate = null;
try {
    /*
     * Put all your routes here
     */
    if ($task['appname'] == 'log' && $task['taskname'] == 'onfile') {
        list($res, $error, $postponeDate) = log_file($task['data']);
    } else {
        $res = 404;
        $error = 'ERROR : Route not found.';
    }

} catch (\Exception $e) {
    $res = 500;
    $error = $e->getCode() . ' ' . $e->getMessage();
}

// gestion des retours
if ($res == 200) {
    $taskDao->remove($task['id']);
    myLog('TRACE : Task finished correctly (id=' . $task['id'] . ') .');
} elseif ($res == 500) {
    $taskDao->error($task['id'], $error);
    myLog("ERROR : Task finished on error 500 : '{$task['id']}' - $error .");
} elseif ($res == 400 || $res == 404) {
    $taskDao->error($task['id'], $error);
    myLog("ERROR : Task finished on error $res : '{$task['id']}' - '{$task['appname']}' - '{$task['taskname']}' - $error.");
} elseif ($res == 'postpone') {
    // put your code here
    myLog("INFO : Task postponed to '$postponeDate' (id='{$task['id']}') .");
} else {
    $taskDao->error($task['id'], 'unknown error');
    myLog("ERROR : Task finished on unknown error : '{$task['id']}' .");
}

// sleep(1);
// print("End of process\n");
exit(0);


/**
 * Fonction d'execution d'une tache "log/file".
 * Ceci est une fonction de test, le mieux pour vous est de ne pas mettre toutes vos fonctions dans ce fichier.
 *
 * @param array $data Donnees de la taches
 * @return array Etat de sortie
 */
function log_file($data) {
    // verifs
    if (!isset($data['file']) || empty($data['file']) || !isset($data['word']) || empty($data['word']))
        return (array(400, "Bad parameters", null));

    // action
    @file_put_contents("log/{$data['file']}", $data['word'] . "\n", FILE_APPEND);

    // retour
    return (array(200, null, null));
}

?>