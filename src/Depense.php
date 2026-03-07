<?php

namespace src;

use PDO;
use PDOException;

class Depense
{
    private $bd;

    public function __construct(PDO $bd)
    {
        $this->bd = $bd;
    }

    /**
     * Créer une nouvelle dépense
     */
    public function createDepense($data)
    {
        $sql = "INSERT INTO depense (type, product_id, manager_id, cout, date, description) 
                VALUES (:type, :product_id, :manager_id, :cout, :date, :description)";

        $req = $this->bd->prepare($sql);
        return $req->execute([
            'type'        => $data['type'] ?? 'autres',
            'product_id'  => $data['product_id'] ?? null,
            'manager_id'  => $data['manager_id'] ?? null,
            'cout'        => $data['cout'],
            'date'        => $data['date'] ?? date('Y-m-d H:i:s'),
            'description' => $data['description'] ?? ($data['descrption'] ?? '')
        ]);
    }

    /**
     * Récupérer toutes les dépenses
     */
    public function getAllDepenses($dateFrom = null, $dateTo = null, $type = null, $limit = 100)
    {
        $sql = "SELECT d.*, p.name AS product_name, u.name AS manager_name
                FROM depense d
                LEFT JOIN products p ON d.product_id = p.id
                LEFT JOIN users u ON d.manager_id = u.id
                WHERE 1=1";
        $params = [];
        if ($dateFrom && $dateTo) {
            $sql .= " AND d.date BETWEEN :date_from AND :date_to";
            $params['date_from'] = $dateFrom;
            $params['date_to'] = $dateTo;
        }
        if ($type !== null && $type !== '') {
            $sql .= " AND d.type = :type";
            $params['type'] = $type;
        }
        $sql .= " ORDER BY d.date DESC, d.id DESC LIMIT " . (int) $limit;
        $req = $this->bd->prepare($sql);
        $req->execute($params);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les dépenses par type
     */
    public function getDepensesByType($type)
    {
        $sql = "SELECT * FROM depense WHERE type = :type ORDER BY date DESC";
        $req = $this->bd->prepare($sql);
        $req->execute(['type' => $type]);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les dépenses par produit
     */
    public function getDepensesByProductId($productId)
    {
        $sql = "SELECT * FROM depense WHERE product_id = :product_id ORDER BY date DESC";
        $req = $this->bd->prepare($sql);
        $req->execute(['product_id' => $productId]);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }
}
