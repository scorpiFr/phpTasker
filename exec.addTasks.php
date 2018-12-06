#!/usr/bin/php
<?php
include_once('lib/FineDatabase.php');
include_once('Agent.php');
include_once('config/config.php');

// initialisations
$words = array(
'Insecte',
'Betterave',
'Traction',
'Football',
'Planète',
'Lunettes',
'Bon',
'Grille-pain',
'Brun',
'Traîneau',
'ballaster',
'bractéate',
'clinostat',
'gégé',
'obsidionaux',
'papyrologie',
'pomifère',
'sous-pubien',
'states',
'verticuteur',
'ammonéen',
'bouillable',
'candiacois',
'contumace',
'flouze',
'réenfouir',
'superposables',
'transvasement',
'trouble-fête',
'vinosité',
'cherveusien',
'claustrophilie',
'drisser',
'dévideuse',
'enfranger',
'gilbertain',
'incidentelle',
'infoboîte',
'poincinia',
'vulpinique',
'adinferroises',
'anti-malthusien',
'chlorphéniramine',
'entrevue',
'exafarad',
'gueuleton',
'hypobole',
'mobilisation',
'ourler',
'proto-micronésienne'
);


// connexion sql
$db = new FineDatabase($databaseHost, $databaseUser, $databasePassword, $databaseName, $databasePort);

// creation de la table si besoin
{
    $taskDao = new TaskDao($db);
    $taskDao->createTable();
}

// ajout de 500 taches
$taskAgent = new Agent($db);
for($i = 0; $i < 500; $i++) {
    $key = array_rand($words);
    $params = [
        'file'=>'words.txt',
        'word'=> $words[$key],
    ];
    $taskAgent->doIt('log', 'onfile', $params);
    unset($key, $params);
}

// retour
