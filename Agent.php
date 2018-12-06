<?php

include_once('lib/TaskDao.php');

/**
 * Objet permettant l'envoi d'ordres d'execution différé.
 * Agent de liaison avec les script exterieurss pour l'ajout de taches.
 *
 * @author	Camille Khalaghi <camille.khalaghi@finemedia.fr>
 */

class Agent {
	/** Nom de la machine locale. */
	private $_hostName = null;
	/** @var TaskDao $_taskdao DAO d'accès à la base de données. */
	private $_taskdao = null;

	/**
	 * Constructeur.
	 * @param	\FineDatabase	$db	Connexion à la base de données.
	 */
	public function __construct(\FineDatabase $db) {
		$this->_taskdao = new TaskDao($db);
	}

	/**
	 * Ajout d'un ordre d'execution.
	 * @param	string	$appName	Nom de l'application.
	 * @param	string	$taskName	Nom de la tâche.
	 * @param	mixed	$data		Données associées à la tâche.
     * @param	string	$priority	(optionnel) Priorité d'exécution ('normal', 'low', 'high', 'immediate'). 'normal' par défaut.
     * @param	string	$datemin	(optionnel) date minimum d'execution de la tache, sours format Y-m-d. default : '1970-01-01'.
	 */
	public function doIt($appName, $taskName, $data, $priority='normal', $datemin='1970-01-01') {
		$jsonData = empty($data) ? '' : json_encode($data);
		if (empty($this->_hostName))
			$this->_hostName = gethostname();
		$this->_taskdao->add($appName, $taskName, $jsonData, $this->_hostName, $priority, $datemin);
	}
}

?>
