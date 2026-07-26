<?php

namespace src;

use PDO;
use PDOException;

class Order
{
    private $bd;

    public function __construct(PDO $bd)
    {
        $this->bd = $bd;
    }

    public function getTotalOrders()
    {
        $query = "SELECT COUNT(*) as total FROM orders";
        $stmt = $this->bd->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['total'];
    }

    public function getOrdersByStatus($status)
    {
        $query = "SELECT COUNT(*) as total FROM orders WHERE newstat = :status";
        $stmt = $this->bd->prepare($query);
        $stmt->execute(['status' => $status]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['total'];
    }

    public function CreateOrder($data)
    {
        $params = [
            'product_id' => (int) ($data['product_id'] ?? 0),
            'pack_id' => !empty($data['pack_id']) ? (int) $data['pack_id'] : null,
            'purchase_price' => (int) ($data['purchase_price'] ?? 0),
            'total_price' => (int) ($data['total_price'] ?? 0),
            'unit_price' => (int) ($data['unit_price'] ?? $data['total_price'] ?? 0),
            'quantity' => (int) ($data['quantity'] ?? 1),
            'client_name' => $data['client_name'] ?? '',
            'client_country' => $data['client_country'],
            'client_adress' => $data['client_adress'] ?? '',
            'client_phone' => $data['client_phone'] ?? '',
            'client_note' => $data['client_note'] ?? null,
            'newstat' => 'new',
            'manager_id' => (int) ($data['manager_id'] ?? 0),
        ];

        try {
            $req = $this->bd->prepare("
                INSERT INTO orders 
                (product_id, pack_id, purchase_price, unit_price, total_price, quantity, client_name, client_country, client_adress, client_phone, client_note, newstat, manager_id) 
                VALUES 
                (:product_id, :pack_id, :purchase_price, :unit_price, :total_price, :quantity, :client_name, :client_country, :client_adress, :client_phone, :client_note, :newstat, :manager_id)
            ");
            $req->execute($params);
            return true;
        } catch (PDOException $e) {
            $isMissingUnitPriceColumn =
                $e->getCode() === '42S22'
                || stripos($e->getMessage(), "Unknown column 'unit_price'") !== false;

            if (!$isMissingUnitPriceColumn) {
                throw $e;
            }

            unset($params['unit_price']);
            $fallback = $this->bd->prepare("
                INSERT INTO orders 
                (product_id, pack_id, purchase_price, total_price, quantity, client_name, client_country, client_adress, client_phone, client_note, newstat, manager_id) 
                VALUES 
                (:product_id, :pack_id, :purchase_price, :total_price, :quantity, :client_name, :client_country, :client_adress, :client_phone, :client_note, :newstat, :manager_id)
            ");
            $fallback->execute($params);
        }

        return true;
    }

    public function getOrderById($id)
    {
        $sql = "SELECT * FROM orders WHERE id = :id LIMIT 1";
        $req = $this->bd->prepare($sql);
        $req->execute(['id' => $id]);
        return $req->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllOrders()
    {
        $sql = "
        SELECT
            o.id            AS order_id,
            o.product_id,
            o.pack_id,
            o.quantity,
            o.purchase_price,
            o.total_price,
            o.client_name,
            o.client_country,
            o.client_phone,
            o.client_adress,
            o.client_note,
            o.manager_note,
            o.manager_id,
            o.newstat,
            o.created_at,
            o.updated_at,
            COALESCE(pc.selling_price, 0) AS unit_price,
            COALESCE(p.name, 'Produit supprimé') AS product_name,
            COALESCE(pp.name, '') AS pack_name,
            COALESCE(u.name, '—') AS assistant_name,
            uc.name AS assistant_country_name,
            uc.code AS assistant_country_code
        FROM orders o
        LEFT JOIN products p ON p.id = o.product_id
        LEFT JOIN users u ON u.id = o.manager_id
        LEFT JOIN countries uc ON (u.country = uc.code OR u.country = CAST(uc.id AS CHAR))
        LEFT JOIN product_countries pc 
            ON pc.product_id = p.id 
           AND pc.country_id = o.client_country
        LEFT JOIN product_packs pp ON pp.id = o.pack_id
        ORDER BY o.id DESC
    ";

        $req = $this->bd->prepare($sql);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }


    public function updateOrder(array $data)
    {
        $sql = "UPDATE orders 
            SET quantity = :quantity,
                total_price = :total_price,
                newstat = :newstat,
                manager_note = :manager_note,
                updated_at = :updated_at
            WHERE id = :id";

        $req = $this->bd->prepare($sql);
        $req->execute([
            'quantity' => $data['quantity'],
            'total_price' => $data['total_price'],
            'newstat' => $data['newstat'],
            'manager_note' => $data['manager_note'],
            'updated_at' => $data['updated_at'],
            'id' => $data['id'],
        ]);
        return true;
    }

    /**
     * Transfère toutes les commandes d'un assistant vers son remplaçant pour un
     * produit. L'historique doit suivre la nouvelle assistante, y compris pour
     * les commandes livrées ou annulées.
     */
    public function reassignPendingOrders(int $productId, int $oldManagerId, int $newManagerId): int
    {
        $sql = "UPDATE orders
            SET manager_id = :new_manager_id,
                updated_at = :updated_at
            WHERE product_id = :product_id
              AND manager_id = :old_manager_id";

        $req = $this->bd->prepare($sql);
        $req->execute([
            'new_manager_id' => $newManagerId,
            'updated_at' => date('Y-m-d H:i:s'),
            'product_id' => $productId,
            'old_manager_id' => $oldManagerId,
        ]);

        return $req->rowCount();
    }

    /**
     * Transfère l'historique complet d'une assistante vers sa remplaçante.
     */
    public function reassignManagerOrders(int $oldManagerId, int $newManagerId): int
    {
        if ($oldManagerId < 1 || $newManagerId < 1 || $oldManagerId === $newManagerId) {
            throw new \InvalidArgumentException("Assistantes source ou destination invalides.");
        }

        $req = $this->bd->prepare(
            "UPDATE orders
             SET manager_id = :new_manager_id, updated_at = :updated_at
             WHERE manager_id = :old_manager_id"
        );
        $req->execute([
            'new_manager_id' => $newManagerId,
            'old_manager_id' => $oldManagerId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $req->rowCount();
    }

    /**
     * Réattribue les commandes du produit qui appartiennent à une assistante
     * retirée (ou qui sont encore non assignées). Le même pays est privilégié ;
     * à défaut, la première assistante sélectionnée reçoit la commande.
     */
    public function reassignProductOrders(int $productId, array $managerIds): int
    {
        $managerIds = array_values(array_unique(array_filter(
            array_map('intval', $managerIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($productId < 1 || $managerIds === []) {
            throw new \InvalidArgumentException('Produit ou liste des assistantes invalide.');
        }

        $placeholders = implode(',', array_fill(0, count($managerIds), '?'));
        $managerStmt = $this->bd->prepare(
            "SELECT u.id, c.id AS country_id
             FROM users u
             LEFT JOIN countries c
               ON (u.country = CAST(c.id AS CHAR)
                   OR u.country = c.code
                   OR u.country = c.phone_code)
             WHERE u.id IN ($placeholders)
               AND u.role = 0
               AND u.is_active = 1"
        );
        $managerStmt->execute($managerIds);
        $managers = $managerStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($managers) !== count($managerIds)) {
            throw new \InvalidArgumentException('Une assistante sélectionnée est invalide ou inactive.');
        }

        $fallbackManagerId = (int) $managers[0]['id'];
        $managerByCountry = [];
        foreach ($managers as $manager) {
            $countryId = (int) ($manager['country_id'] ?? 0);
            if ($countryId > 0 && !isset($managerByCountry[$countryId])) {
                $managerByCountry[$countryId] = (int) $manager['id'];
            }
        }

        $ordersStmt = $this->bd->prepare(
            "SELECT id, client_country
             FROM orders
             WHERE product_id = ?
               AND manager_id NOT IN ($placeholders)"
        );
        $ordersStmt->execute(array_merge([$productId], $managerIds));
        $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($orders === []) {
            return 0;
        }

        $startedTransaction = !$this->bd->inTransaction();
        if ($startedTransaction) {
            $this->bd->beginTransaction();
        }

        try {
            $updateStmt = $this->bd->prepare(
                "UPDATE orders
                 SET manager_id = :manager_id, updated_at = :updated_at
                 WHERE id = :order_id"
            );
            $updatedAt = date('Y-m-d H:i:s');
            $updatedCount = 0;

            foreach ($orders as $order) {
                $countryId = (int) ($order['client_country'] ?? 0);
                $targetManagerId = $managerByCountry[$countryId] ?? $fallbackManagerId;
                $updateStmt->execute([
                    'manager_id' => $targetManagerId,
                    'updated_at' => $updatedAt,
                    'order_id' => (int) $order['id'],
                ]);
                $updatedCount += $updateStmt->rowCount();
            }

            if ($startedTransaction) {
                $this->bd->commit();
            }

            return $updatedCount;
        } catch (\Throwable $error) {
            if ($startedTransaction && $this->bd->inTransaction()) {
                $this->bd->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Nombre de commandes non finalisées (ni livrées ni annulées) assignées à cet
     * assistant — utilisé pour avertir l'admin avant de supprimer un compte.
     */
    public function countPendingOrdersByManager(int $managerId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM orders WHERE manager_id = :manager_id AND newstat NOT IN ('deliver', 'canceled')";
        $req = $this->bd->prepare($sql);
        $req->execute(['manager_id' => $managerId]);
        return (int) $req->fetch(PDO::FETCH_ASSOC)['c'];
    }

    /**
     * Retire une assistante de toutes ses commandes lorsqu'aucune remplaçante
     * n'existe. Cela évite de conserver un manager_id pointant vers un compte
     * supprimé.
     */
    public function unassignManager(int $managerId): int
    {
        $sql = "UPDATE orders
            SET manager_id = 0,
                updated_at = :updated_at
            WHERE manager_id = :manager_id";

        $req = $this->bd->prepare($sql);
        $req->execute([
            'updated_at' => date('Y-m-d H:i:s'),
            'manager_id' => $managerId,
        ]);

        return $req->rowCount();
    }

    public function getOrdersByUserId($userId)
    {
        $sql = "
        SELECT
            o.id AS order_id,
            o.product_id,
            o.pack_id,
            o.quantity,
            o.purchase_price,
            o.total_price,
            o.client_name,
            o.client_country,
            o.client_phone,
            o.client_adress,
            o.client_note,
            o.manager_note,
            o.manager_id,
            o.newstat,
            o.created_at,
            o.updated_at,
            COALESCE(pc.selling_price, 0) AS unit_price,
            COALESCE(p.name, 'Produit supprimé') AS product_name,
            COALESCE(pp.name, '') AS pack_name,
            COALESCE(u.name, '—') AS assistant_name,
            uc.name AS assistant_country_name,
            uc.code AS assistant_country_code
        FROM orders o
        LEFT JOIN products p ON p.id = o.product_id
        LEFT JOIN users u ON u.id = o.manager_id
        LEFT JOIN countries uc ON (u.country = uc.code OR u.country = CAST(uc.id AS CHAR))
        LEFT JOIN product_countries pc
            ON pc.product_id = p.id
           AND pc.country_id = o.client_country
        LEFT JOIN product_packs pp ON pp.id = o.pack_id
        WHERE o.manager_id = :manager_id
        ORDER BY o.id DESC
    ";

        $req = $this->bd->prepare($sql);
        $req->execute(['manager_id' => $userId]);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrdersByStatuses(array $statuses, ?int $limit = null, int $offset = 0)
    {
        if (empty($statuses)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($statuses) as $index => $status) {
            $key = 'status_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = "
        SELECT
            o.id AS order_id,
            o.product_id,
            o.pack_id,
            o.quantity,
            o.purchase_price,
            o.total_price,
            o.client_name,
            o.client_country,
            o.client_phone,
            o.client_adress,
            o.client_note,
            o.manager_note,
            o.manager_id,
            o.newstat,
            o.created_at,
            o.updated_at,
            COALESCE(pc.selling_price, 0) AS unit_price,
            COALESCE(p.name, 'Produit supprimé') AS product_name,
            COALESCE(pp.name, '') AS pack_name,
            COALESCE(u.name, '—') AS assistant_name,
            uc.name AS assistant_country_name,
            uc.code AS assistant_country_code
        FROM orders o
        LEFT JOIN products p ON p.id = o.product_id
        LEFT JOIN users u ON u.id = o.manager_id
        LEFT JOIN countries uc ON (u.country = uc.code OR u.country = CAST(uc.id AS CHAR))
        LEFT JOIN product_countries pc
            ON pc.product_id = p.id
           AND pc.country_id = o.client_country
        LEFT JOIN product_packs pp ON pp.id = o.pack_id
        WHERE o.newstat IN (" . implode(',', $placeholders) . ")
        ORDER BY o.id DESC
        " . ($limit !== null ? "LIMIT " . (int)$limit . " OFFSET " . (int)$offset : "") . "
    ";

        $req = $this->bd->prepare($sql);
        $req->execute($params);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrdersByStatusesAndUserId(array $statuses, int $userId, ?int $limit = null, int $offset = 0)
    {
        if (empty($statuses)) {
            return [];
        }

        $placeholders = [];
        $params = ['manager_id' => $userId];
        foreach (array_values($statuses) as $index => $status) {
            $key = 'status_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = "
        SELECT
            o.id AS order_id,
            o.product_id,
            o.pack_id,
            o.quantity,
            o.purchase_price,
            o.total_price,
            o.client_name,
            o.client_country,
            o.client_phone,
            o.client_adress,
            o.client_note,
            o.manager_note,
            o.manager_id,
            o.newstat,
            o.created_at,
            o.updated_at,
            COALESCE(pc.selling_price, 0) AS unit_price,
            COALESCE(p.name, 'Produit supprimé') AS product_name,
            COALESCE(pp.name, '') AS pack_name,
            COALESCE(u.name, '—') AS assistant_name,
            uc.name AS assistant_country_name,
            uc.code AS assistant_country_code
        FROM orders o
        LEFT JOIN products p ON p.id = o.product_id
        LEFT JOIN users u ON u.id = o.manager_id
        LEFT JOIN countries uc ON (u.country = uc.code OR u.country = CAST(uc.id AS CHAR))
        LEFT JOIN product_countries pc
            ON pc.product_id = p.id
           AND pc.country_id = o.client_country
        LEFT JOIN product_packs pp ON pp.id = o.pack_id
        WHERE o.newstat IN (" . implode(',', $placeholders) . ")
          AND o.manager_id = :manager_id
        ORDER BY o.id DESC
        " . ($limit !== null ? "LIMIT " . (int)$limit . " OFFSET " . (int)$offset : "") . "
    ";

        $req = $this->bd->prepare($sql);
        $req->execute($params);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Détail complet d'une commande (avec jointures produit/assistant/pays) pour
     * alimenter la modale d'édition chargée à la demande côté admin.
     */
    public function getOrderDetailsById(int $id): ?array
    {
        $sql = "
        SELECT
            o.id AS order_id,
            o.product_id,
            o.pack_id,
            o.quantity,
            o.purchase_price,
            o.total_price,
            o.client_name,
            o.client_country,
            o.client_phone,
            o.client_adress,
            o.client_note,
            o.manager_note,
            o.manager_id,
            o.newstat,
            o.created_at,
            o.updated_at,
            COALESCE(pc.selling_price, 0) AS unit_price,
            COALESCE(p.name, 'Produit supprimé') AS product_name,
            COALESCE(pp.name, '') AS pack_name,
            COALESCE(u.name, '—') AS assistant_name,
            uc.name AS assistant_country_name,
            uc.code AS assistant_country_code
        FROM orders o
        LEFT JOIN products p ON p.id = o.product_id
        LEFT JOIN users u ON u.id = o.manager_id
        LEFT JOIN countries uc ON (u.country = uc.code OR u.country = CAST(uc.id AS CHAR))
        LEFT JOIN product_countries pc
            ON pc.product_id = p.id
           AND pc.country_id = o.client_country
        LEFT JOIN product_packs pp ON pp.id = o.pack_id
        WHERE o.id = :id
        LIMIT 1
    ";

        $req = $this->bd->prepare($sql);
        $req->execute(['id' => $id]);
        $order = $req->fetch(PDO::FETCH_ASSOC);
        return $order ?: null;
    }



    public function deleteOrder($id)
    {
        $sql = "DELETE FROM orders WHERE id = :id";
        $req = $this->bd->prepare($sql);
        return $req->execute(['id' => $id]);
    }


    public function getOrdersToDay()
    {
        $sql = "
        SELECT 
            orders.id AS order_id,
            orders.quantity,
            orders.total_price,
            orders.client_name,
            orders.client_country,
            orders.client_phone,
            orders.client_adress,
            orders.newstat,
            orders.updated_at,
            COALESCE(products.name, 'Produit supprimé') AS product_name,
            COALESCE(products.image, '') AS product_image,
            COALESCE(u.name, '—') AS assistant_name,
            uc.name AS assistant_country_name,
            uc.code AS assistant_country_code
        FROM orders
        LEFT JOIN products ON products.id = orders.product_id
        LEFT JOIN users u ON u.id = orders.manager_id
        LEFT JOIN countries uc ON (u.country = uc.code OR u.country = CAST(uc.id AS CHAR))
        WHERE orders.updated_at >= CURDATE()
          AND orders.updated_at < CURDATE() + INTERVAL 1 DAY
          AND orders.newstat = 'deliver'
        ORDER BY orders.id DESC
    ";

        $req = $this->bd->prepare($sql);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getOrdersToDayByUserId($userId)
    {
        $sql = "
        SELECT 
            orders.id AS order_id,
            orders.quantity,
            orders.total_price,
            orders.client_name,
            orders.client_country,
            orders.client_phone,
            orders.client_adress,
            orders.manager_id,
            orders.newstat,
            orders.updated_at,
            COALESCE(products.name, 'Produit supprimé') AS product_name,
            COALESCE(products.image, '') AS product_image,
            COALESCE(u.name, '—') AS assistant_name,
            uc.name AS assistant_country_name,
            uc.code AS assistant_country_code
        FROM orders
        LEFT JOIN products ON products.id = orders.product_id
        LEFT JOIN users u ON u.id = orders.manager_id
        LEFT JOIN countries uc ON (u.country = uc.code OR u.country = CAST(uc.id AS CHAR))
        WHERE orders.updated_at >= CURDATE()
          AND orders.updated_at < CURDATE() + INTERVAL 1 DAY
          AND orders.newstat = 'deliver'
          AND orders.manager_id = :manager_id
        ORDER BY orders.id DESC
    ";

        $req = $this->bd->prepare($sql);
        $req->execute(['manager_id' => $userId]);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer TOUTES les commandes livrées (archives)
    public function getAllDeliveredOrders()
    {
        $sql = "
        SELECT DISTINCT
            orders.id AS order_id,
            orders.product_id,
            orders.pack_id,
            orders.quantity,
            orders.total_price,
            orders.client_name,
            orders.client_country,
            orders.client_phone,
            orders.client_adress,
            orders.client_note,
            orders.manager_note,
            orders.manager_id,
            orders.newstat,
            orders.created_at,
            orders.updated_at,
            COALESCE(products.name, 'Produit supprimé') AS product_name,
            COALESCE(products.image, '') AS product_image,
            COALESCE(product_packs.name, '') AS pack_name,
            CASE WHEN orders.quantity > 0 THEN ROUND(orders.total_price / orders.quantity, 2) ELSE orders.total_price END AS unit_price
        FROM orders
        LEFT JOIN products ON products.id = orders.product_id
        LEFT JOIN product_packs ON product_packs.id = orders.pack_id
        WHERE orders.newstat = 'deliver'
        ORDER BY orders.updated_at DESC, orders.id DESC
        ";

        $req = $this->bd->prepare($sql);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les commandes livrées d'un manager spécifique (archives)
    public function getDeliveredOrdersByUserId($userId)
    {
        $sql = "
        SELECT DISTINCT
            orders.id AS order_id,
            orders.product_id,
            orders.pack_id,
            orders.quantity,
            orders.total_price,
            orders.client_name,
            orders.client_country,
            orders.client_phone,
            orders.client_adress,
            orders.client_note,
            orders.manager_note,
            orders.manager_id,
            orders.newstat,
            orders.created_at,
            orders.updated_at,
            COALESCE(products.name, 'Produit supprimé') AS product_name,
            COALESCE(products.image, '') AS product_image,
            COALESCE(product_packs.name, '') AS pack_name,
            CASE WHEN orders.quantity > 0 THEN ROUND(orders.total_price / orders.quantity, 2) ELSE orders.total_price END AS unit_price
        FROM orders
        LEFT JOIN products ON products.id = orders.product_id
        LEFT JOIN product_packs ON product_packs.id = orders.pack_id
        WHERE orders.newstat = 'deliver'
          AND orders.manager_id = :user_id
        ORDER BY orders.updated_at DESC, orders.id DESC
        ";

        $req = $this->bd->prepare($sql);
        $req->execute(['user_id' => $userId]);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrdersAfterId(int $lastId)
    {
        $sql = "
        SELECT
            o.id AS order_id,
            o.client_name,
            o.client_phone,
            o.client_adress,
            o.client_note,
            o.manager_note,
            o.quantity,
            o.total_price,
            o.newstat,
            o.created_at,
            o.updated_at,
            CASE WHEN o.quantity > 0 THEN ROUND(o.total_price / o.quantity, 2) ELSE o.total_price END AS unit_price,
            COALESCE(p.name, pp.name, 'Produit') AS product_name
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN product_packs pp ON o.pack_id = pp.id
        WHERE o.id > :lastId
        ORDER BY o.id ASC
        ";
        $req = $this->bd->prepare($sql);
        $req->execute(['lastId' => $lastId]);
        return $req->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getOrdersAfterIdByUserId(int $lastId, int $userId)
    {
        $sql = "
        SELECT
            o.id AS order_id,
            o.client_name,
            o.client_phone,
            o.client_adress,
            o.client_note,
            o.manager_note,
            o.quantity,
            o.total_price,
            o.newstat,
            o.created_at,
            o.updated_at,
            CASE WHEN o.quantity > 0 THEN ROUND(o.total_price / o.quantity, 2) ELSE o.total_price END AS unit_price,
            COALESCE(p.name, pp.name, 'Produit') AS product_name
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN product_packs pp ON o.pack_id = pp.id
        WHERE o.id > :lastId AND (p.user_id = :manager_id OR o.manager_id = :manager_id2)
        ORDER BY o.id ASC
        ";
        $req = $this->bd->prepare($sql);
        $req->execute([
            'lastId' => $lastId,
            'manager_id' => $userId,
            'manager_id2' => $userId
        ]);
        return $req->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getMaxOrderId(): int
    {
        $query = "SELECT MAX(id) as max_id FROM orders";
        $stmt = $this->bd->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['max_id'];
    }

    public function getMaxOrderIdByUserId(int $userId): int
    {
        $sql = "
        SELECT MAX(o.id) as max_id 
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE p.user_id = :manager_id OR o.manager_id = :manager_id2
        ";
        $req = $this->bd->prepare($sql);
        $req->execute(['manager_id' => $userId, 'manager_id2' => $userId]);
        $result = $req->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['max_id'];
    }
}
