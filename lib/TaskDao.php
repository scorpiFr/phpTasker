<?php

include_once('Dao.php');

class TaskDao extends Dao {
    protected $_tableName = 'task';
    protected $_idField = 'id';


    public function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `task` ( 
                tas_i_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                tas_d_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
				tas_t_update DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				tas_s_appname varchar(30) NOT NULL DEFAULT '',
				tas_s_taskname varchar(30) NOT NULL DEFAULT '',
				tas_e_priority ENUM('low', 'normal', 'high', 'immediate'),
				tas_e_status ENUM('waiting', 'reserved', 'started', 'finished', 'error'),
				tas_t_minstartdate DATETIME DEFAULT '1970-01-01',
				tas_s_token varchar(100) NOT NULL DEFAULT '',
				tas_s_host varchar(50) NOT NULL DEFAULT '',
				tas_s_data TEXT NOT NULL ,
				tas_s_error varchar(255) NOT NULL DEFAULT '',
				INDEX tas_e_priority (tas_e_priority)
		);";
        $this->_db->exec($sql);
        unset($sql);
    }
    /**
     * Récupère un ordre.
     * @param	int	$id	Identifiant de l'ordre.
     * @return	array	Hash de l'ordre. null si rien trouvé.
     */
    public function getTask($id) {
        $sql = "SELECT	tas_i_id AS id,
				tas_d_creation AS creationDate,
				tas_t_update AS updateDate,
				tas_s_appname AS appname,
				tas_s_taskname AS taskname,
				tas_e_priority AS priority,
				tas_e_status AS status,
				tas_t_minstartdate as startdate,
				tas_s_token AS token,
				tas_s_host AS host,
				tas_s_data AS data
			FROM task
			WHERE tas_i_id = '" . $this->_db->quote($id) . "'";
        $res = $this->_db->queryOne($sql);
        if (!empty($res['data']))
            $res['data'] = json_decode($res['data'], true);

        unset($sql);
        return ($res);
    }

    /**
     * Ajout d'un ordre.
     * @param	string	$appName	Nom de l'application.
     * @param	string	$taskName	Nom de la tâche.
     * @param	string	$data		Données associées à la tâche.
     * @param	string	$host		(optionnel) Nom de la machine.
     * @param	string	$priority	(optionnel) Priorité d'exécution ('normal', 'low', 'high'). 'normal' par défaut.
     * @param	string	$datemin	(optionnel) date minimum d'execution de la tache, sours format Y-m-d. default : '1970-01-01'.
     */
    public function add($appName, $taskName, $data, $host=null, $priority='normal', $datemin='1970-01-01') {
        $sql = "INSERT INTO task 
			SET tas_d_creation = NOW(),
			    tas_t_update = NOW(),
			    tas_s_appname = '" . $this->_db->quote($appName) . "',
			    tas_s_taskname = '" . $this->_db->quote($taskName) . "',
			    tas_e_priority = '" . $this->_db->quote($priority) . "',
			    tas_e_status = 'waiting',
			    tas_s_token = '',
			    tas_s_data = '" . $this->_db->quote($data) . "',
			    tas_s_host = '" . $this->_db->quote($host) . "',
                tas_t_minstartdate = '" . $this->_db->quote($datemin) . "'
            ";
        $this->_db->exec($sql);
        unset($sql);
    }

    /**
     * Prise de 10 ids de taches.
     * @param	int	    $nbrTask	(optionnel) Nombre de taches a prendre. 10 par defaut.
     * @param	string	$hostname	(optionnel) Machine donneuse d'ordre.
     * @return	array|null	Liste d'ordres.
     */
    function getWaitingTasks($nbrTask=10, $hostname=null) {
        if ($nbrTask <= 0)
            return (null);
        $token = hash('md5', time() . mt_rand() . mt_rand() . mt_rand() . mt_rand());
        if (!empty($hostname))
            $token = "$hostname-$token";
        // reservation
        $where = array();
        $where[] = "tas_e_status = 'waiting'";
        $where[] = "tas_s_token = ''";
        $where[] = "tas_t_minstartdate < NOW()";
        if (!empty($hostname))
            $where[] = "tas_s_host = '" . $this->_db->quote($hostname) . "'";
        $sql = "UPDATE task
			SET tas_s_token = '" . $this->_db->quote($token) . "',
			    tas_e_status = 'reserved'
			WHERE " . implode(" AND ", $where) . "
			ORDER BY tas_e_priority DESC, tas_i_id ASC
			LIMIT $nbrTask";
        $this->_db->exec($sql);
        unset($sql, $where);

        // récupération
        $sql = "SELECT  tas_i_id AS id
			FROM task
			WHERE tas_s_token = '" . $this->_db->quote($token) . "'
			ORDER BY tas_e_priority DESC, tas_i_id ASC";

        $result = $this->_db->queryAll($sql);
        unset($sql);
        if (empty($result)) {
            unset($result);
            return null;
        }
        return ($result);
    }

    /**
     * Change le status d'une tâche.
     * @param	int	$id	Identifiant de la tâche.
     * @param	int	$status	Nouveau status ('reserved','started','finished', 'error').
     */
    function setStatus($id, $status) {
        $sql = "UPDATE task
			SET tas_e_status = '" . $this->_db->quote($status) . "'
			WHERE tas_i_id = '" . $this->_db->quote($id) . "'";
        $this->_db->exec($sql);
    }
    /**
     * Supprime une tâche.
     * @param	int	$id	Identifiant de la tâche.
     */
    function remove($id) {
        $sql = "DELETE FROM task
			WHERE tas_i_id = '" . $this->_db->quote($id) . "'";
        $this->_db->exec($sql);
        unset($sql);
    }
    /**
     * Met une tache en erreur.
     * @param	int	$id	        Identifiant de la tâche.
     * @param	string	$error	(optionnel) Message d'erreur.
     */
    function error($id, $error='') {
        $sql = "UPDATE task
                SET 
                tas_e_status = 'error',
                tas_s_error = '" . $this->_db->quote($error) . "'
			WHERE tas_i_id = '" . $this->_db->quote($id) . "'";
        $this->_db->exec($sql);
        unset($sql);
    }

    /**
     * Relance la tache pour une autre heure.
     * @param	int	    $id	    Identifiant de la tâche.
     * @param	string	$date	Nouvelle date, au format Y-m-d
     */
    function postpone($id, $date) {
        $sql = "UPDATE task
                SET 
			      tas_s_token = '',
			      tas_e_status = 'waiting',
			      tas_t_minstartdate = '" . $this->_db->quote($date) . "'
			WHERE tas_i_id = '" . $this->_db->quote($id) . "'";
        $this->_db->exec($sql);
        unset($sql);
    }

    /**
     * Compte les taches
     * @param string (optionnel) $hostname
     *
     * @return array|null Nombre par status.
     */
    function countByStatus($hostname) {
        // lancement de la requete
        {
            $where = array();
            $where[] = "tas_t_minstartdate < NOW()";
            if (!empty($hostname))
                $where[] = "tas_s_host = '" . $this->_db->quote($hostname) . "'";

            $sql = "SELECT
                COUNT(tas_e_status) AS statusNbr,
                tas_e_status AS status
			FROM task
            WHERE " . implode(" AND ", $where) . "
            GROUP BY tas_e_status;";
            $result = $this->_db->queryAll($sql);
            if (empty($result)) {
                unset($where, $sql, $result);
                return (null);
            }
            unset($where, $sql);
        }

        // indexation
        {
            $res = array();
            foreach($result as $line)
                $res[$line['status']] = $line['statusNbr'];
            unset($result, $line);
        }
        // completion des infos vides
        {
            $fields = array('waiting', 'reserved', 'started', 'finished', 'error');
            foreach ($fields as $field) {
                if (!isset($res[$field]))
                    $res[$field] = 0;
            }
            unset($fields, $field);
        }
        return ($res);
    }

    /**
     * Remet a waiting les taches reservees depuis plus d'une heure.
     * @param $hostname
     */
    function cleanTasks($hostname) {
        $sql = "UPDATE task
            SET tas_e_status = 'waiting',
                tas_s_token = ''
			WHERE 
				tas_e_status = 'reserved'
			AND tas_t_update < NOW() - INTERVAL 1 HOUR
            ";
        $this->_db->exec($sql);
        unset($sql);
    }


}
