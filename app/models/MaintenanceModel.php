<?php

class MaintenanceModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // دریافت سرویس‌های ادواری پیش رو
    public function getUpcomingMaintenance($limit = 5) {
        $query = "SELECT m.id, m.title, m.description, m.due_date, m.status,
                  a.asset_tag, a.name as asset_name, mt.name as maintenance_type
                  FROM maintenance m
                  INNER JOIN assets a ON m.asset_id = a.id
                  INNER JOIN maintenance_types mt ON m.type_id = mt.id
                  WHERE m.status = 'scheduled' AND m.due_date >= CURDATE()
                  ORDER BY m.due_date ASC
                  LIMIT ?";
        return $this->db->query($query, [$limit]);
    }
}