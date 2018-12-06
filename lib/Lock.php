<?php

class Lock
{
    private $_lockFile;

    /** @var ressource $_fileResosurce */
    private $_fileRessource;

    public function __construct($lockFile) {
        $this->_lockFile = $lockFile;
    }

    /**
     * Lock un fichier
     *
     * @return bool   False si le verrou est deja verouille. true si ok.
     */
    public function lock() {
        // ouverture du fichier
        $this->_fileRessource = fopen($this->_lockFile, 'c+');
        if (empty($this->_fileRessource))
            return false;

        // recuperation du pid ecrit dans le fichier
        $pid = fgets($this->_fileRessource);

        // verification de l'existance du process inscrit dans le fichier
        if (!empty($pid)) {
            $gpid = posix_getpgid($pid);
            if (!empty($gpid)) {
                unset($pgid, $pid);
                return (false); // le process est toujours vivant
            }
        }

        // inscription de notre pid dans le fichier
        $this->updateLock();

        // retour
        unset($pid, $gpid);
        return (true);
    }

    /**
     * Met a jour le fichier de lock, en y reecrivant le pid actuel (dans le cas ou le pid du programme a change.)
     */
    function updateLock() {
        $mypid = '' . getmypid();
        fseek($this->_fileRessource, 0, SEEK_SET);
        fwrite($this->_fileRessource, $mypid, strlen($mypid));
        unset($mypid);
    }
}
