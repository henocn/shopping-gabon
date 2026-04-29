<?php

namespace src;

use PDO;
use PDOException;

class Country
{
    private $bd;

    public function __construct(PDO $bd)
    {
        $this->bd = $bd;
    }

    /**
     * Retourne l’id du pays à partir de son code (ex. TG, GN).
     */
    public function getIdByCode($code)
    {
        $req = $this->bd->prepare("SELECT id FROM countries WHERE code = ? LIMIT 1");
        $req->execute([$code]);
        $row = $req->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Retourne le code pays à partir de l’id (ex. TG pour id=1).
     */
    public function getCodeById($id)
    {
        $req = $this->bd->prepare("SELECT code FROM countries WHERE id = ? LIMIT 1");
        $req->execute([(int) $id]);
        $row = $req->fetch(PDO::FETCH_ASSOC);
        return $row ? trim($row['code']) : null;
    }

    /**
     * Liste tous les pays.
     */
    public function getAll()
    {
        $sql = "SELECT * FROM countries ORDER BY name ASC";
        $req = $this->bd->prepare($sql);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

}
