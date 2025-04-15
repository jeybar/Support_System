<?php

require_once __DIR__ . '/../core/Database.php';

class Role {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection(); // دریافت اتصال به پایگاه داده
    }

    // دریافت همه نقش‌ها
    public function getAllRoles($limit, $offset) {
        $query = "SELECT * FROM roles LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // دریافت نقش ها بدون محدودیت
    public function getAllRolesWithoutLimit() {
        $query = "SELECT * FROM roles";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ایجاد نقش جدید
    public function createRole($name, $description) {
        $query = "INSERT INTO roles (role_name, description) VALUES (:role_name, :description)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':role_name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        return $stmt->execute();
    }

    // حذف نقش
    public function deleteRole($id) {
        $query = "DELETE FROM roles WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // دریافت دسترسی‌های نقش
    public function getPermissionsByRoleId($roleId) {
        $query = "SELECT permission_id FROM role_permissions WHERE role_id = :role_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN); // بازگرداندن فقط ستون permission_id
    }

    // به‌روزرسانی دسترسی‌های نقش
    public function updateRolePermissions($roleId, $permissions) {
        $this->db->beginTransaction();
    
        try {
            // حذف دسترسی‌های فعلی
            $query = "DELETE FROM role_permissions WHERE role_id = :role_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();
    
            // افزودن دسترسی‌های جدید
            $query = "INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
            $stmt = $this->db->prepare($query);
            foreach ($permissions as $permissionId) {
                $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
                $stmt->bindParam(':permission_id', $permissionId, PDO::PARAM_INT);
                $stmt->execute();
            }
    
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // دریافت نقش بر اساس ID
    public function getRoleById($id) {
        $query = "SELECT * FROM roles WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // به‌روزرسانی نقش
    public function updateRole($id, $name, $description) {
        $query = "UPDATE roles SET name = :name, description = :description WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        return $stmt->execute();
    }

    //جستجوی نقش
    public function searchRoles($keyword, $filter = null) {
        // تعریف ستون‌های معتبر برای فیلتر
        $validFilters = ['role_name', 'description'];
    
        // شروع کوئری
        $query = "SELECT * FROM roles WHERE 1";
    
        // بررسی کلیدواژه و فیلتر
        if (!empty($keyword)) {
            if ($filter && in_array($filter, $validFilters)) {
                // اگر فیلتر معتبر است، جستجو در ستون مشخص‌شده انجام شود
                $query .= " AND $filter LIKE :keyword";
            } else {
                // اگر فیلتر مشخص نشده یا نامعتبر است، جستجو در هر دو ستون انجام شود
                $query .= " AND (role_name LIKE :keyword OR description LIKE :keyword)";
            }
        }
    
        // آماده‌سازی و اجرای کوئری
        $stmt = $this->db->prepare($query);
        if (!empty($keyword)) {
            $stmt->bindValue(':keyword', '%' . $keyword . '%', PDO::PARAM_STR);
        }
        $stmt->execute();
    
        // بازگرداندن نتایج
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //بررسی تکراری بودن نام نقش
    public function roleExists($name) {
        $query = "SELECT COUNT(*) FROM roles WHERE role_name = :name";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    //محاسبه تعداد کل رکوردها
    public function getTotalRolesCount() {
        $query = "SELECT COUNT(*) AS total FROM roles";
        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // دریافت نقش‌ها با اعمال فیلترها
    public function getFilteredRoles($filters, $limit, $offset, $sortBy = 'id', $order = 'asc') {
        // اعتبارسنجی ستون مرتب‌سازی
        $validSortColumns = ['id', 'role_name', 'description'];
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'id'; // مقدار پیش‌فرض
        }

        // اعتبارسنجی ترتیب مرتب‌سازی
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

        // ساخت شرط WHERE
        $whereConditions = [];
        $params = [];

        if (!empty($filters['role_name'])) {
            $whereConditions[] = "r.role_name LIKE :role_name";
            $params[':role_name'] = '%' . $filters['role_name'] . '%';
        }

        if (!empty($filters['description'])) {
            $whereConditions[] = "r.description LIKE :description";
            $params[':description'] = '%' . $filters['description'] . '%';
        }

        // اگر فیلتر دسترسی انتخاب شده باشد
        if (!empty($filters['permission'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = :permission_id)";
            $params[':permission_id'] = $filters['permission'];
        }

        // ساخت بخش WHERE کوئری
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        }

        // ساخت و اجرای کوئری
        $query = "
            SELECT r.*
            FROM roles r
            $whereClause
            ORDER BY $sortBy $order
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($query);

        // اضافه کردن پارامترهای صفحه‌بندی
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        // بایند کردن پارامترها
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // دریافت تعداد کل نقش‌ها با اعمال فیلترها
    public function getFilteredRolesCount($filters) {
        // ساخت شرط WHERE
        $whereConditions = [];
        $params = [];

        if (!empty($filters['role_name'])) {
            $whereConditions[] = "r.role_name LIKE :role_name";
            $params[':role_name'] = '%' . $filters['role_name'] . '%';
        }

        if (!empty($filters['description'])) {
            $whereConditions[] = "r.description LIKE :description";
            $params[':description'] = '%' . $filters['description'] . '%';
        }

        // اگر فیلتر دسترسی انتخاب شده باشد
        if (!empty($filters['permission'])) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = :permission_id)";
            $params[':permission_id'] = $filters['permission'];
        }

        // ساخت بخش WHERE کوئری
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        }

        // ساخت و اجرای کوئری
        $query = "
            SELECT COUNT(*) as count
            FROM roles r
            $whereClause
        ";

        $stmt = $this->db->prepare($query);

        // بایند کردن پارامترها
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    // دریافت همه نقش‌ها با مرتب‌سازی
    public function getAllRolesSorted($sortBy = 'id', $order = 'asc', $limit = 10, $offset = 0) {
        // اعتبارسنجی ستون مرتب‌سازی
        $validSortColumns = ['id', 'role_name', 'description'];
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'id'; // مقدار پیش‌فرض
        }

        // اعتبارسنجی ترتیب مرتب‌سازی
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

        $query = "SELECT * FROM roles ORDER BY $sortBy $order LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}