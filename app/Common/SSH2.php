<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/7/2019
 * Time: 3:07 PM
 */

namespace App\Common;


class SSH2 {

    private $host;

    private $port;

    private $userName;

    private $password;

    /**
     * @var mixed
     */
    private $conn = false;

    private $isAuthenticated = false;

    public function __construct(string $host, string $userName, string $password, int $port=22, bool $immediateConnect=false) {
        $this->host = $host;
        $this->port = $port;
        $this->userName = $userName;
        $this->password = $password;
        if($immediateConnect) {
            $this->connect()->authenticate();
        }
    }

    public function connect() {
        if($this->conn === false) {
            $this->conn = ssh2_connect($this->host, $this->port);
            if($this->conn === false) {
                throw new \RuntimeException("Cannot Connect to remote asterisk server via SSH2");
            }
        }
        return $this;
    }

    public function authenticate() {
        $this->isAuthenticated = ssh2_auth_password($this->conn, $this->userName, $this->password);
        if($this->isAuthenticated === false) {
            throw new \RuntimeException("Cannot authenticate on remote asterisk server via SSH2");
        }
        return $this;
    }

    public function getSFTPHandler() {
        $sftpHandler = ssh2_sftp($this->conn);
        return $sftpHandler;
    }

    public function downloadFile($remotePath, $localPath) {
//        logger()->error($remotePath);
//        logger()->error($localPath);
        return ssh2_scp_recv($this->conn, $remotePath, $localPath);
    }

    public function cmd(string $command) {
        $stream = ssh2_exec($this->conn, $command);
        stream_set_blocking($stream, true);
        if($stream === false) {
            throw new \RuntimeException("Cannot execute given command on remote asterisk server via SSH2");
        }
        $returnedString = stream_get_contents($stream);
        if($returnedString === false) {
            throw new \RuntimeException("Executed command, couldn't get response from stream via SSH2!");
        }
        if(empty($returnedString)) {
            return "";
        }
        return $returnedString;
    }

}
