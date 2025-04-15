<?php

require_once __DIR__ . '/../core/Database.php';

class Asset {
    private $db;

    public function __construct() {
        try {
            // دریافت اتصال به پایگاه داده از طریق کلاس Database
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            error_log("Error connecting to database: " . $e->getMessage());
            throw new Exception("خطا در اتصال به پایگاه داده. لطفاً با مدیر سیستم تماس بگیرید.");
        }
    }

    /**
     * ایجاد تجهیز جدید
     * 
     * @param array $data اطلاعات تجهیز
     * @return int|bool شناسه تجهیز جدید یا false در صورت خطا
     */
    public function createAsset($data) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // دریافت category_id مربوط به مدل انتخاب شده
            $modelId = $data['model_id'];
            $stmt = $this->db->prepare("SELECT category_id FROM asset_models WHERE id = :model_id");
            $stmt->bindValue(':model_id', $modelId, PDO::PARAM_INT);
            $stmt->execute();
            $modelData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$modelData) {
                throw new Exception("مدل انتخاب شده معتبر نیست.");
            }
            
            // تعریف فیلدهای پایه و مقادیر پیش‌فرض
            $fields = [
                'name' => $data['name'],
                'asset_tag' => $data['asset_tag'],
                'model_id' => $data['model_id'],
                'category_id' => $modelData['category_id'], // استفاده از category_id مرتبط با مدل
                'serial_number' => $data['serial_number'] ?? '',
                'status' => $data['status'],
                'notes' => $data['notes'] ?? '',
                'created_at' => 'NOW()',
                'updated_at' => 'NOW()'
            ];
            
            // اضافه کردن فیلدهای اختیاری در صورت وجود
            if (!empty($data['purchase_date'])) {
                $fields['purchase_date'] = $data['purchase_date'];
            }
                        
            if (!empty($data['warranty_months'])) {
                $fields['warranty_months'] = $data['warranty_months'];
            }
            
            // بررسی اینکه آیا location به صورت id است یا مقدار متنی
            if (!empty($data['location_id'])) {
                $fields['location_id'] = $data['location_id'];
            } elseif (!empty($data['location'])) {
                $fields['location'] = $data['location'];
            }
            
            // ساخت کوئری دینامیک
            $columnNames = array_keys($fields);
            
            // ساخت بخش فیلدها و پارامترها برای کوئری
            $columns = implode(', ', $columnNames);
            $placeholders = implode(', ', array_map(function($col) {
                return $col === 'created_at' || $col === 'updated_at' ? 'NOW()' : ":$col";
            }, $columnNames));
            
            $query = "INSERT INTO assets ($columns) VALUES ($placeholders)";
            
            $stmt = $this->db->prepare($query);
            
            // بایند کردن پارامترها (به جز NOW() که مستقیماً در کوئری استفاده می‌شود)
            foreach ($fields as $key => $value) {
                if ($key !== 'created_at' && $key !== 'updated_at') {
                    $paramType = PDO::PARAM_STR;
                    if (is_int($value) || ctype_digit($value)) {
                        $paramType = PDO::PARAM_INT;
                    } elseif (is_bool($value)) {
                        $paramType = PDO::PARAM_BOOL;
                    } elseif (is_null($value)) {
                        $paramType = PDO::PARAM_NULL;
                    }
                    $stmt->bindValue(":$key", $value, $paramType);
                }
            }
            
            // اجرای کوئری
            $stmt->execute();
            
            // دریافت شناسه تجهیز جدید
            $assetId = $this->db->lastInsertId();
            
            // اضافه کردن مشخصات سخت‌افزاری
            if (!empty($data['specifications']) && is_array($data['specifications'])) {
                $specQuery = "
                    INSERT INTO asset_specifications (
                        asset_id, spec_name, spec_value, created_at
                    ) VALUES (
                        :asset_id, :spec_name, :spec_value, NOW()
                    )
                ";
                $specStmt = $this->db->prepare($specQuery);
                
                foreach ($data['specifications'] as $spec) {
                    $specStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
                    $specStmt->bindParam(':spec_name', $spec['name'], PDO::PARAM_STR);
                    $specStmt->bindParam(':spec_value', $spec['value'], PDO::PARAM_STR);
                    $specStmt->execute();
                }
            }
            
            // ذخیره employee_number و computer_name در metadata
            if (!empty($data['employee_number']) || !empty($data['computer_name'])) {
                $this->ensureMetadataTableExists();
                
                if (!empty($data['employee_number'])) {
                    $this->saveAssetMetadata($assetId, 'employee_number', $data['employee_number']);
                }
                
                if (!empty($data['computer_name'])) {
                    $this->saveAssetMetadata($assetId, 'computer_name', $data['computer_name']);
                }
            }
            
            // اگر کاربر تعیین شده باشد، تخصیص تجهیز به کاربر
            if (!empty($data['user_id']) || !empty($data['assigned_to'])) {
                $userId = $data['user_id'] ?? $data['assigned_to'];
                $this->assignAssetToUser($assetId, $userId, $data['notes'] ?? null);
            }
            
            // تایید تراکنش
            $this->db->commit();
            
            return $assetId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error in createAsset: " . $e->getMessage());
            
            // بررسی نوع خطا برای شماره برچسب تکراری
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && 
                strpos($e->getMessage(), 'asset_tag') !== false) {
                throw new Exception("شماره اموال تجهیز تکراری است. لطفاً شماره دیگری وارد کنید.");
            }
            
            // انتقال خطا به کنترلر
            throw new Exception("خطا در ثبت تجهیز در پایگاه داده.");
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in createAsset: " . $e->getMessage());
            throw $e; // انتقال خطا به کنترلر
        }
    }

    /**
     * اطمینان از وجود جدول metadata
     */
    private function ensureMetadataTableExists() {
        try {
            // بررسی وجود جدول asset_metadata
            $checkTable = $this->db->query("SHOW TABLES LIKE 'asset_metadata'");
            if ($checkTable->rowCount() == 0) {
                // اگر جدول وجود نداشت، آن را ایجاد کنیم
                $this->db->exec("
                    CREATE TABLE `asset_metadata` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `asset_id` int(11) NOT NULL,
                        `meta_key` varchar(100) NOT NULL,
                        `meta_value` text DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        PRIMARY KEY (`id`),
                        KEY `asset_id` (`asset_id`),
                        KEY `meta_key` (`meta_key`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            }
        } catch (PDOException $e) {
            error_log("Error checking/creating metadata table: " . $e->getMessage());
        }
    }

    /**
     * ذخیره متادیتا برای تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param string $key کلید متادیتا
     * @param mixed $value مقدار متادیتا
     * @return bool نتیجه عملیات
     */
    private function saveAssetMetadata($assetId, $key, $value) {
        try {
            $query = "
                INSERT INTO asset_metadata (asset_id, meta_key, meta_value, created_at)
                VALUES (:asset_id, :meta_key, :meta_value, NOW())
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $key, PDO::PARAM_STR);
            $stmt->bindParam(':meta_value', $value, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in saveAssetMetadata: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param array $data اطلاعات جدید تجهیز
     * @return bool نتیجه عملیات
     */
    public function updateAsset($assetId, $data) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // آماده‌سازی کوئری برای به‌روزرسانی تجهیز
            $query = "
                UPDATE assets 
                SET 
                    name = :name,
                    asset_tag = :asset_tag,
                    model_id = :model_id,
                    serial_number = :serial_number,
                    purchase_date = :purchase_date,
                    purchase_cost = :purchase_cost,
                    warranty_months = :warranty_months,
                    status = :status,
                    location_id = :location_id,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :asset_id
            ";
            
            $stmt = $this->db->prepare($query);
            
            // بایند کردن پارامترها
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':asset_tag', $data['asset_tag'], PDO::PARAM_STR);
            $stmt->bindParam(':model_id', $data['model_id'], PDO::PARAM_INT);
            $stmt->bindParam(':serial_number', $data['serial_number'], PDO::PARAM_STR);
            $stmt->bindParam(':purchase_date', $data['purchase_date'], PDO::PARAM_STR);
            $stmt->bindParam(':purchase_cost', $data['purchase_cost'], PDO::PARAM_STR);
            $stmt->bindParam(':warranty_months', $data['warranty_months'], PDO::PARAM_INT);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindParam(':location_id', $data['location_id'], PDO::PARAM_INT);
            $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            
            // اجرای کوئری
            $stmt->execute();
            
            // به‌روزرسانی مشخصات سخت‌افزاری
            if (!empty($data['specifications']) && is_array($data['specifications'])) {
                // حذف مشخصات قبلی
                $deleteSpecsQuery = "DELETE FROM asset_specifications WHERE asset_id = :asset_id";
                $deleteStmt = $this->db->prepare($deleteSpecsQuery);
                $deleteStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
                $deleteStmt->execute();
                
                // افزودن مشخصات جدید
                $specQuery = "
                    INSERT INTO asset_specifications (
                        asset_id, spec_name, spec_value, created_at
                    ) VALUES (
                        :asset_id, :spec_name, :spec_value, NOW()
                    )
                ";
                $specStmt = $this->db->prepare($specQuery);
                
                foreach ($data['specifications'] as $spec) {
                    $specStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
                    $specStmt->bindParam(':spec_name', $spec['name'], PDO::PARAM_STR);
                    $specStmt->bindParam(':spec_value', $spec['value'], PDO::PARAM_STR);
                    $specStmt->execute();
                }
            }
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in updateAsset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return bool نتیجه عملیات
     */
    public function deleteAsset($assetId) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // حذف مشخصات سخت‌افزاری
            $deleteSpecsQuery = "DELETE FROM asset_specifications WHERE asset_id = :asset_id";
            $deleteSpecsStmt = $this->db->prepare($deleteSpecsQuery);
            $deleteSpecsStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $deleteSpecsStmt->execute();
            
            // حذف تخصیص‌های تجهیز
            $deleteAssignmentsQuery = "DELETE FROM asset_assignments WHERE asset_id = :asset_id";
            $deleteAssignmentsStmt = $this->db->prepare($deleteAssignmentsQuery);
            $deleteAssignmentsStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $deleteAssignmentsStmt->execute();
            
            // حذف سوابق تعمیر و نگهداری
            $deleteMaintenanceQuery = "DELETE FROM maintenance_logs WHERE asset_id = :asset_id";
            $deleteMaintenanceStmt = $this->db->prepare($deleteMaintenanceQuery);
            $deleteMaintenanceStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $deleteMaintenanceStmt->execute();
            
            // حذف ارتباط با درخواست‌های کار
            $deleteTicketAssetsQuery = "DELETE FROM ticket_assets WHERE asset_id = :asset_id";
            $deleteTicketAssetsStmt = $this->db->prepare($deleteTicketAssetsQuery);
            $deleteTicketAssetsStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $deleteTicketAssetsStmt->execute();
            
            // حذف تجهیز
            $deleteAssetQuery = "DELETE FROM assets WHERE id = :asset_id";
            $deleteAssetStmt = $this->db->prepare($deleteAssetQuery);
            $deleteAssetStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $deleteAssetStmt->execute();
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in deleteAsset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت اطلاعات یک تجهیز با شناسه
     * 
     * @param int $assetId شناسه تجهیز
     * @return array|bool اطلاعات تجهیز یا false در صورت خطا
     */
    public function getAssetById($assetId) {
        try {
            // کوئری دریافت اطلاعات تجهیز
            $query = "
                SELECT 
                    a.*,
                    m.name AS model_name,
                    m.manufacturer_id,
                    man.name AS manufacturer_name,
                    c.name AS category_name,
                    s.name AS supplier_name,
                    l.name AS location_name,
                    (
                        SELECT aa.user_id
                        FROM asset_assignments aa
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_user_id,
                    (
                        SELECT u.fullname
                        FROM users u
                        JOIN asset_assignments aa ON u.id = aa.user_id
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_user_name,
                    (
                        SELECT aa.assigned_at
                        FROM asset_assignments aa
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_date
                FROM 
                    assets a
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    manufacturers man ON m.manufacturer_id = man.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                LEFT JOIN 
                    locations l ON a.location_id = l.id
                WHERE 
                    a.id = :asset_id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$asset) {
                return false;
            }
            
            // دریافت مشخصات سخت‌افزاری
            $specsQuery = "
                SELECT spec_name, spec_value
                FROM asset_specifications
                WHERE asset_id = :asset_id
                ORDER BY spec_name
            ";
            
            $specsStmt = $this->db->prepare($specsQuery);
            $specsStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $specsStmt->execute();
            
            $asset['specifications'] = $specsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // دریافت تاریخچه تخصیص
            $assignmentHistoryQuery = "
                SELECT 
                    aa.id,
                    aa.user_id,
                    u.fullname AS user_name,
                    aa.assigned_at,
                    aa.returned_at,
                    aa.notes,
                    aa.is_current
                FROM 
                    asset_assignments aa
                LEFT JOIN 
                    users u ON aa.user_id = u.id
                WHERE 
                    aa.asset_id = :asset_id
                ORDER BY 
                    aa.assigned_at DESC
            ";
            
            $assignmentHistoryStmt = $this->db->prepare($assignmentHistoryQuery);
            $assignmentHistoryStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $assignmentHistoryStmt->execute();
            
            $asset['assignment_history'] = $assignmentHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // دریافت تاریخچه تعمیر و نگهداری
            $maintenanceHistoryQuery = "
                SELECT 
                    ml.id,
                    ml.maintenance_date,
                    ml.maintenance_type,
                    ml.performed_by,
                    u.fullname AS technician_name,
                    ml.cost,
                    ml.notes
                FROM 
                    maintenance_logs ml
                LEFT JOIN 
                    users u ON ml.performed_by = u.id
                WHERE 
                    ml.asset_id = :asset_id
                ORDER BY 
                    ml.maintenance_date DESC
            ";
            
            $maintenanceHistoryStmt = $this->db->prepare($maintenanceHistoryQuery);
            $maintenanceHistoryStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $maintenanceHistoryStmt->execute();
            
            $asset['maintenance_history'] = $maintenanceHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // دریافت درخواست‌های کار مرتبط
            $ticketsQuery = "
                SELECT 
                    t.id,
                    t.title,
                    t.status,
                    t.priority,
                    t.created_at,
                    t.resolved_at
                FROM 
                    tickets t
                JOIN 
                    ticket_assets ta ON t.id = ta.ticket_id
                WHERE 
                    ta.asset_id = :asset_id
                ORDER BY 
                    t.created_at DESC
            ";
            
            $ticketsStmt = $this->db->prepare($ticketsQuery);
            $ticketsStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $ticketsStmt->execute();
            
            $asset['tickets'] = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // محاسبه اطلاعات گارانتی
            if (!empty($asset['purchase_date']) && !empty($asset['warranty_months'])) {
                $purchaseDate = new DateTime($asset['purchase_date']);
                $warrantyEnd = clone $purchaseDate;
                $warrantyEnd->modify("+{$asset['warranty_months']} months");
                
                $now = new DateTime();
                $asset['warranty_end_date'] = $warrantyEnd->format('Y-m-d');
                $asset['is_in_warranty'] = ($now <= $warrantyEnd);
                
                if ($asset['is_in_warranty']) {
                    $interval = $now->diff($warrantyEnd);
                    $asset['warranty_remaining'] = [
                        'days' => $interval->days,
                        'formatted' => $interval->format('%y سال، %m ماه و %d روز')
                    ];
                } else {
                    $asset['warranty_remaining'] = [
                        'days' => 0,
                        'formatted' => 'منقضی شده'
                    ];
                }
            } else {
                $asset['warranty_end_date'] = null;
                $asset['is_in_warranty'] = false;
                $asset['warranty_remaining'] = [
                    'days' => 0,
                    'formatted' => 'نامشخص'
                ];
            }
            
            // محاسبه سن تجهیز
            if (!empty($asset['purchase_date'])) {
                $purchaseDate = new DateTime($asset['purchase_date']);
                $now = new DateTime();
                $interval = $purchaseDate->diff($now);
                
                $asset['age'] = [
                    'days' => $interval->days,
                    'formatted' => $interval->format('%y سال، %m ماه و %d روز')
                ];
            } else {
                $asset['age'] = [
                    'days' => 0,
                    'formatted' => 'نامشخص'
                ];
            }
            
            return $asset;
        } catch (PDOException $e) {
            error_log("Error in getAssetById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جستجوی دارایی‌ها
     * 
     * @param array $filters فیلترهای جستجو
     * @param string $sort فیلد مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی (ASC یا DESC)
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array نتایج جستجو
     */
    public function searchAssets($filters = [], $sort = 'created_at', $order = 'DESC', $page = 1, $perPage = 10) {
        try {
            // بررسی نوع اتصال به دیتابیس
            $connection = $this->db;
            
            // بررسی وجود ستون‌های مورد نیاز
            $columns = $this->getTableColumns('assets');
            $hasAssignedTo = in_array('assigned_to', $columns);
            $hasLocation = in_array('location', $columns);
            
            $where = [];
            $params = [];
            
            // اعمال فیلترها با توجه به ستون‌های موجود
            if (!empty($filters['asset_tag'])) {
                $where[] = "a.asset_tag LIKE ?";
                $params[] = "%" . $filters['asset_tag'] . "%";
            }
            
            if (!empty($filters['category_id'])) {
                $where[] = "a.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['model_id'])) {
                $where[] = "a.model_id = ?";
                $params[] = $filters['model_id'];
            }
            
            if (!empty($filters['status'])) {
                $where[] = "a.status = ?";
                $params[] = $filters['status'];
            }
            
            // فقط اگر ستون assigned_to وجود داشته باشد
            if ($hasAssignedTo && !empty($filters['assigned_to'])) {
                $where[] = "a.assigned_to = ?";
                $params[] = $filters['assigned_to'];
            }
            
            if (!empty($filters['serial'])) {
                $where[] = "a.serial LIKE ?";
                $params[] = "%" . $filters['serial'] . "%";
            }
            
            // فقط اگر ستون location وجود داشته باشد
            if ($hasLocation && !empty($filters['location'])) {
                $where[] = "a.location LIKE ?";
                $params[] = "%" . $filters['location'] . "%";
            }
            
            if (!empty($filters['name'])) {
                $where[] = "a.name LIKE ?";
                $params[] = "%" . $filters['name'] . "%";
            }
            
            // ساخت شرط WHERE
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            // ساخت JOIN با توجه به ستون‌های موجود
            $joinClause = "LEFT JOIN asset_categories c ON a.category_id = c.id 
                        LEFT JOIN asset_models m ON a.model_id = m.id";
                        
            if ($hasAssignedTo) {
                $joinClause .= " LEFT JOIN users u ON a.assigned_to = u.id";
            }
            
            // تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total FROM assets a 
                        $joinClause 
                        $whereClause";
            
            // اجرای کوئری شمارش
            $totalCount = 0;
            
            if ($connection instanceof PDO) {
                $stmt = $connection->prepare($countQuery);
                $stmt->execute($params);
                $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            }
            
            // محاسبه تعداد صفحات
            $totalPages = ceil($totalCount / $perPage);
            
            // محدود کردن شماره صفحه
            $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
            
            // محاسبه OFFSET
            $offset = ($page - 1) * $perPage;
            
            // انتخاب فیلدهای مناسب با توجه به ستون‌های موجود
            $selectFields = "a.*, 
                            c.name as category_name, 
                            m.name as model_name";
                            
            if ($hasAssignedTo) {
                $selectFields .= ", u.username as assigned_username,
                                u.fullname as assigned_fullname";
                                
                if (in_array('employee_number', $this->getTableColumns('users'))) {
                    $selectFields .= ", u.employee_number as assigned_employee_number";
                }
            }
            
            // کوئری اصلی
            $query = "SELECT $selectFields
                    FROM assets a 
                    $joinClause 
                    $whereClause 
                    ORDER BY $sort $order 
                    LIMIT $perPage OFFSET $offset";
            
            // اجرای کوئری
            $assets = [];
            
            if ($connection instanceof PDO) {
                $stmt = $connection->prepare($query);
                $stmt->execute($params);
                $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return [
                'assets' => $assets,
                'total' => $totalCount,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages
            ];
        } catch (Exception $e) {
            error_log("Error in searchAssets: " . $e->getMessage());
            return [
                'assets' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 0
            ];
        }
    }

    /**
     * تخصیص تجهیز به کاربر
     * 
     * @param int $assetId شناسه تجهیز
     * @param int $userId شناسه کاربر
     * @param string|null $notes یادداشت
     * @return bool نتیجه عملیات
     */
    public function assignAssetToUser($assetId, $userId, $notes = null) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // غیرفعال کردن تخصیص‌های فعلی
            $deactivateQuery = "
                UPDATE asset_assignments
                SET is_current = 0, returned_at = NOW()
                WHERE asset_id = :asset_id AND is_current = 1
            ";
            
            $deactivateStmt = $this->db->prepare($deactivateQuery);
            $deactivateStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $deactivateStmt->execute();
            
            // ایجاد تخصیص جدید
            $assignQuery = "
                INSERT INTO asset_assignments (
                    asset_id, user_id, assigned_at, notes, is_current
                ) VALUES (
                    :asset_id, :user_id, NOW(), :notes, 1
                )
            ";
            
            $assignStmt = $this->db->prepare($assignQuery);
            $assignStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $assignStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $assignStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $assignStmt->execute();
            
            // به‌روزرسانی وضعیت تجهیز
            $updateAssetQuery = "
                UPDATE assets
                SET status = 'assigned', updated_at = NOW()
                WHERE id = :asset_id
            ";
            
            $updateAssetStmt = $this->db->prepare($updateAssetQuery);
            $updateAssetStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $updateAssetStmt->execute();
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in assignAssetToUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * بازگرداندن تجهیز از کاربر
     * 
     * @param int $assetId شناسه تجهیز
     * @param string|null $notes یادداشت
     * @return bool نتیجه عملیات
     */
    public function unassignAsset($assetId, $notes = null) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // به‌روزرسانی تخصیص فعلی
            $unassignQuery = "
                UPDATE asset_assignments
                SET is_current = 0, returned_at = NOW(), notes = CONCAT(IFNULL(notes, ''), ' | ', :notes)
                WHERE asset_id = :asset_id AND is_current = 1
            ";
            
            $unassignStmt = $this->db->prepare($unassignQuery);
            $unassignStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $unassignStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $unassignStmt->execute();
            
            // به‌روزرسانی وضعیت تجهیز
            $updateAssetQuery = "
                UPDATE assets
                SET status = 'available', updated_at = NOW()
                WHERE id = :asset_id
            ";
            
            $updateAssetStmt = $this->db->prepare($updateAssetQuery);
            $updateAssetStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $updateAssetStmt->execute();
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in unassignAsset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ثبت تعمیر و نگهداری برای تجهیز
     * 
     * @param array $data اطلاعات تعمیر و نگهداری
     * @return int|bool شناسه سابقه تعمیر یا false در صورت خطا
     */
    public function logMaintenance($data) {
        try {
            $query = "
                INSERT INTO maintenance_logs (
                    asset_id, maintenance_date, maintenance_type, 
                    performed_by, cost, notes, created_at
                ) VALUES (
                    :asset_id, :maintenance_date, :maintenance_type, 
                    :performed_by, :cost, :notes, NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':asset_id', $data['asset_id'], PDO::PARAM_INT);
            $stmt->bindParam(':maintenance_date', $data['maintenance_date'], PDO::PARAM_STR);
            $stmt->bindParam(':maintenance_type', $data['maintenance_type'], PDO::PARAM_STR);
            $stmt->bindParam(':performed_by', $data['performed_by'], PDO::PARAM_INT);
            $stmt->bindParam(':cost', $data['cost'], PDO::PARAM_STR);
            $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
            
            $stmt->execute();
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error in logMaintenance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت تجهیز‌های یک کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return array لیست تجهیز‌های کاربر
     */
    public function getUserAssets($userId) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    a.status,
                    m.name AS model_name,
                    c.name AS category_name,
                    aa.assigned_at,
                    (
                        SELECT COUNT(*)
                        FROM maintenance_logs ml
                        WHERE ml.asset_id = a.id
                    ) AS maintenance_count,
                    (
                        SELECT COUNT(*)
                        FROM ticket_assets ta
                        JOIN tickets t ON ta.ticket_id = t.id
                        WHERE ta.asset_id = a.id
                    ) AS ticket_count
                FROM 
                    assets a
                JOIN 
                    asset_assignments aa ON a.id = aa.asset_id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    aa.user_id = :user_id AND aa.is_current = 1
                ORDER BY 
                    aa.assigned_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserAssets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تاریخچه تخصیص یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array تاریخچه تخصیص
     */
    public function getAssetAssignmentHistory($assetId) {
        try {
            $query = "
                SELECT 
                    aa.id,
                    aa.user_id,
                    u.fullname AS user_name,
                    u.employee_number,
                    d.name AS department_name,
                    aa.assigned_at,
                    aa.returned_at,
                    aa.notes,
                    aa.is_current
                FROM 
                    asset_assignments aa
                LEFT JOIN 
                    users u ON aa.user_id = u.id
                LEFT JOIN 
                    departments d ON u.department_id = d.id
                WHERE 
                    aa.asset_id = :asset_id
                ORDER BY 
                    aa.assigned_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetAssignmentHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تاریخچه تعمیر و نگهداری یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array تاریخچه تعمیر و نگهداری
     */
    public function getAssetMaintenanceHistory($assetId) {
        try {
            $query = "
                SELECT 
                    ml.id,
                    ml.maintenance_date,
                    ml.maintenance_type,
                    ml.performed_by,
                    u.fullname AS technician_name,
                    ml.cost,
                    ml.notes,
                    ml.created_at
                FROM 
                    maintenance_logs ml
                LEFT JOIN 
                    users u ON ml.performed_by = u.id
                WHERE 
                    ml.asset_id = :asset_id
                ORDER BY 
                    ml.maintenance_date DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetMaintenanceHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت مشخصات سخت‌افزاری یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array مشخصات سخت‌افزاری
     */
    public function getAssetSpecifications($assetId) {
        try {
            $query = "
                SELECT spec_name, spec_value
                FROM asset_specifications
                WHERE asset_id = :asset_id
                ORDER BY spec_name
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetSpecifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * به‌روزرسانی مشخصات سخت‌افزاری یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param array $specifications مشخصات سخت‌افزاری جدید
     * @return bool نتیجه عملیات
     */
    public function updateAssetSpecifications($assetId, $specifications) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // حذف مشخصات قبلی
            $deleteQuery = "DELETE FROM asset_specifications WHERE asset_id = :asset_id";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // افزودن مشخصات جدید
            if (!empty($specifications)) {
                $insertQuery = "
                    INSERT INTO asset_specifications (
                        asset_id, spec_name, spec_value, created_at
                    ) VALUES (
                        :asset_id, :spec_name, :spec_value, NOW()
                    )
                ";
                $insertStmt = $this->db->prepare($insertQuery);
                
                foreach ($specifications as $spec) {
                    $insertStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
                    $insertStmt->bindParam(':spec_name', $spec['name'], PDO::PARAM_STR);
                    $insertStmt->bindParam(':spec_value', $spec['value'], PDO::PARAM_STR);
                    $insertStmt->execute();
                }
            }
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in updateAssetSpecifications: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تغییر وضعیت تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @param string $status وضعیت جدید
     * @param string|null $notes یادداشت
     * @return bool نتیجه عملیات
     */
    public function changeAssetStatus($assetId, $status, $notes = null) {
        try {
            $query = "
                UPDATE assets
                SET status = :status, notes = CONCAT(IFNULL(notes, ''), ' | ', :notes), updated_at = NOW()
                WHERE id = :asset_id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in changeAssetStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت آمار تجهیز‌ها
     * 
     * @return array آمار تجهیز‌ها
     */
    public function getAssetStatistics() {
        try {
            $stats = [];
            
            // تعداد کل تجهیز‌ها
            $totalQuery = "SELECT COUNT(*) FROM assets";
            $stmt = $this->db->query($totalQuery);
            $stats['total'] = $stmt->fetchColumn();
            
            // تعداد تجهیز‌ها بر اساس وضعیت
            $statusQuery = "SELECT status, COUNT(*) as count FROM assets GROUP BY status";
            $stmt = $this->db->query($statusQuery);
            $stats['by_status'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['by_status'][$row['status']] = $row['count'];
            }
            
            // تعداد تجهیز‌ها بر اساس دسته‌بندی
            $categoryQuery = "
                SELECT c.name, COUNT(a.id) as count
                FROM assets a
                JOIN asset_models m ON a.model_id = m.id
                JOIN asset_categories c ON m.category_id = c.id
                GROUP BY c.name
                ORDER BY count DESC
            ";
            $stmt = $this->db->query($categoryQuery);
            $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // تعداد تجهیز‌ها بر اساس سازنده
            $manufacturerQuery = "
                SELECT man.name, COUNT(a.id) as count
                FROM assets a
                JOIN asset_models m ON a.model_id = m.id
                JOIN manufacturers man ON m.manufacturer_id = man.id
                GROUP BY man.name
                ORDER BY count DESC
            ";
            $stmt = $this->db->query($manufacturerQuery);
            $stats['by_manufacturer'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // تعداد تجهیز‌ها بر اساس مکان
            $locationQuery = "
                SELECT l.name, COUNT(a.id) as count
                FROM assets a
                JOIN locations l ON a.location_id = l.id
                GROUP BY l.name
                ORDER BY count DESC
            ";
            $stmt = $this->db->query($locationQuery);
            $stats['by_location'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // تعداد تجهیز‌های تخصیص داده شده و نشده
            $assignmentQuery = "
                SELECT 
                    SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM asset_assignments aa 
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                    ) THEN 1 ELSE 0 END) as assigned,
                    SUM(CASE WHEN NOT EXISTS (
                        SELECT 1 FROM asset_assignments aa 
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                    ) THEN 1 ELSE 0 END) as unassigned
                FROM assets a
            ";
            $stmt = $this->db->query($assignmentQuery);
            $assignmentStats = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['assigned'] = (int)$assignmentStats['assigned'];
            $stats['unassigned'] = (int)$assignmentStats['unassigned'];
            
            // تعداد تجهیز‌های در گارانتی و خارج از گارانتی
            $warrantyQuery = "
                SELECT 
                    SUM(CASE WHEN DATE_ADD(purchase_date, INTERVAL warranty_months MONTH) >= CURDATE() THEN 1 ELSE 0 END) as in_warranty,
                    SUM(CASE WHEN DATE_ADD(purchase_date, INTERVAL warranty_months MONTH) < CURDATE() THEN 1 ELSE 0 END) as out_of_warranty
                FROM assets
                WHERE purchase_date IS NOT NULL AND warranty_months IS NOT NULL
            ";
            $stmt = $this->db->query($warrantyQuery);
            $warrantyStats = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['in_warranty'] = (int)$warrantyStats['in_warranty'];
            $stats['out_of_warranty'] = (int)$warrantyStats['out_of_warranty'];
            
            // ارزش کل تجهیز‌ها
            $valueQuery = "SELECT SUM(purchase_cost) as total_value FROM assets WHERE purchase_cost IS NOT NULL";
            $stmt = $this->db->query($valueQuery);
            $stats['total_value'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in getAssetStatistics: " . $e->getMessage());
            return [
                'total' => 0,
                'by_status' => [],
                'by_category' => [],
                'by_manufacturer' => [],
                'by_location' => [],
                'assigned' => 0,
                'unassigned' => 0,
                'in_warranty' => 0,
                'out_of_warranty' => 0,
                'total_value' => 0
            ];
        }
    }

    /**
     * دریافت تجهیز‌های نزدیک به پایان گارانتی
     * 
     * @param int $days تعداد روزهای آینده
     * @return array لیست تجهیز‌ها
     */
    public function getAssetsNearingWarrantyEnd($days = 30) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    a.purchase_date,
                    a.warranty_months,
                    DATE_ADD(a.purchase_date, INTERVAL a.warranty_months MONTH) as warranty_end_date,
                    DATEDIFF(DATE_ADD(a.purchase_date, INTERVAL a.warranty_months MONTH), CURDATE()) as days_remaining,
                    m.name AS model_name,
                    man.name AS manufacturer_name,
                    (
                        SELECT u.fullname
                        FROM users u
                        JOIN asset_assignments aa ON u.id = aa.user_id
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_to
                FROM 
                    assets a
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    manufacturers man ON m.manufacturer_id = man.id
                WHERE 
                    DATEDIFF(DATE_ADD(a.purchase_date, INTERVAL a.warranty_months MONTH), CURDATE()) BETWEEN 0 AND :days
                ORDER BY 
                    days_remaining
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetsNearingWarrantyEnd: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های نیازمند تعمیر و نگهداری
     * 
     * @param int $days تعداد روزهای گذشته از آخرین تعمیر و نگهداری
     * @return array لیست تجهیز‌ها
     */
    public function getAssetsDueForMaintenance($days = 90) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    a.status,
                    m.name AS model_name,
                    (
                        SELECT MAX(ml.maintenance_date)
                        FROM maintenance_logs ml
                        WHERE ml.asset_id = a.id
                    ) AS last_maintenance_date,
                    DATEDIFF(CURDATE(), (
                        SELECT MAX(ml.maintenance_date)
                        FROM maintenance_logs ml
                        WHERE ml.asset_id = a.id
                    )) AS days_since_maintenance,
                    (
                        SELECT u.fullname
                        FROM users u
                        JOIN asset_assignments aa ON u.id = aa.user_id
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_to
                FROM 
                    assets a
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                WHERE 
                    (
                        SELECT MAX(ml.maintenance_date)
                        FROM maintenance_logs ml
                        WHERE ml.asset_id = a.id
                    ) IS NULL
                    OR
                    DATEDIFF(CURDATE(), (
                        SELECT MAX(ml.maintenance_date)
                        FROM maintenance_logs ml
                        WHERE ml.asset_id = a.id
                    )) >= :days
                ORDER BY 
                    days_since_maintenance DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetsDueForMaintenance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تاریخچه درخواست‌های کار مرتبط با یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array تاریخچه درخواست‌های کار
     */
    public function getAssetTicketHistory($assetId) {
        try {
            $query = "
                SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.status,
                    t.priority,
                    t.created_at,
                    t.resolved_at,
                    t.employee_name AS requester_name,
                    u.fullname AS support_name
                FROM 
                    tickets t
                JOIN 
                    ticket_assets ta ON t.id = ta.ticket_id
                LEFT JOIN 
                    users u ON t.assigned_to = u.id
                WHERE 
                    ta.asset_id = :asset_id
                ORDER BY 
                    t.created_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetTicketHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لیست مدل‌های تجهیز
     * 
     * @param int|null $categoryId شناسه دسته‌بندی (اختیاری)
     * @return array لیست مدل‌ها
     */
    public function getAssetModels($categoryId = null) {
        try {
            $query = "
                SELECT 
                    m.id,
                    m.name,
                    m.model_number,
                    m.manufacturer_id,
                    man.name AS manufacturer_name,
                    m.category_id,
                    c.name AS category_name,
                    (
                        SELECT COUNT(*)
                        FROM assets a
                        WHERE a.model_id = m.id
                    ) AS asset_count
                FROM 
                    asset_models m
                LEFT JOIN 
                    manufacturers man ON m.manufacturer_id = man.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
            ";
            
            $params = [];
            
            if ($categoryId !== null) {
                $query .= " WHERE m.category_id = :category_id";
                $params[':category_id'] = $categoryId;
            }
            
            $query .= " ORDER BY m.name";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetModels: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لیست دسته‌بندی‌های تجهیز
     * 
     * @return array لیست دسته‌بندی‌ها
     */
    public function getAssetCategories() {
        try {
            $query = "
                SELECT 
                    c.id,
                    c.name,
                    c.description,
                    (
                        SELECT COUNT(*)
                        FROM asset_models m
                        WHERE m.category_id = c.id
                    ) AS model_count,
                    (
                        SELECT COUNT(*)
                        FROM assets a
                        JOIN asset_models m ON a.model_id = m.id
                        WHERE m.category_id = c.id
                    ) AS asset_count
                FROM 
                    asset_categories c
                ORDER BY 
                    c.name
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetCategories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لیست سازندگان
     * 
     * @return array لیست سازندگان
     */
    public function getManufacturers() {
        try {
            $query = "
                SELECT 
                    m.id,
                    m.name,
                    m.support_url,
                    m.support_phone,
                    m.support_email,
                    (
                        SELECT COUNT(*)
                        FROM asset_models am
                        WHERE am.manufacturer_id = m.id
                    ) AS model_count,
                    (
                        SELECT COUNT(*)
                        FROM assets a
                        JOIN asset_models am ON a.model_id = am.id
                        WHERE am.manufacturer_id = m.id
                    ) AS asset_count
                FROM 
                    manufacturers m
                ORDER BY 
                    m.name
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getManufacturers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لیست مکان‌ها
     * 
     * @return array لیست مکان‌ها
     */
    public function getLocations() {
        try {
            $query = "
                SELECT 
                    l.id,
                    l.name,
                    l.address,
                    l.city,
                    l.state,
                    l.zip,
                    l.country,
                    (
                        SELECT COUNT(*)
                        FROM assets a
                        WHERE a.location_id = l.id
                    ) AS asset_count
                FROM 
                    locations l
                ORDER BY 
                    l.name
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getLocations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ایجاد یا به‌روزرسانی برنامه تعمیر و نگهداری ادواری
     * 
     * @param array $data اطلاعات برنامه تعمیر و نگهداری
     * @return int|bool شناسه برنامه تعمیر و نگهداری یا false در صورت خطا
     */
    public function createOrUpdateMaintenanceSchedule($data) {
        try {
            if (isset($data['id'])) {
                // به‌روزرسانی برنامه موجود
                $query = "
                    UPDATE maintenance_schedules
                    SET 
                        asset_id = :asset_id,
                        maintenance_type_id = :maintenance_type_id,
                        frequency_days = :frequency_days,
                        next_maintenance_date = :next_maintenance_date,
                        technician_id = :technician_id,
                        notes = :notes,
                        updated_at = NOW()
                    WHERE id = :id
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
            } else {
                // ایجاد برنامه جدید
                $query = "
                    INSERT INTO maintenance_schedules (
                        asset_id, maintenance_type_id, frequency_days, 
                        next_maintenance_date, technician_id, notes, 
                        created_at, updated_at
                    ) VALUES (
                        :asset_id, :maintenance_type_id, :frequency_days, 
                        :next_maintenance_date, :technician_id, :notes, 
                        NOW(), NOW()
                    )
                ";
                
                $stmt = $this->db->prepare($query);
            }
            
            // بایند کردن پارامترهای مشترک
            $stmt->bindParam(':asset_id', $data['asset_id'], PDO::PARAM_INT);
            $stmt->bindParam(':maintenance_type_id', $data['maintenance_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':frequency_days', $data['frequency_days'], PDO::PARAM_INT);
            $stmt->bindParam(':next_maintenance_date', $data['next_maintenance_date'], PDO::PARAM_STR);
            $stmt->bindParam(':technician_id', $data['technician_id'], PDO::PARAM_INT);
            $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
            
            $stmt->execute();
            
            if (isset($data['id'])) {
                return $data['id'];
            } else {
                return $this->db->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("Error in createOrUpdateMaintenanceSchedule: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ثبت انجام تعمیر و نگهداری ادواری
     * 
     * @param array $data اطلاعات انجام تعمیر و نگهداری
     * @return bool نتیجه عملیات
     */
    public function logScheduledMaintenance($data) {
        try {
            // شروع تراکنش
            $this->db->beginTransaction();
            
            // ثبت انجام تعمیر و نگهداری
            $logQuery = "
                INSERT INTO maintenance_logs (
                    asset_id, schedule_id, maintenance_date, maintenance_type, 
                    performed_by, cost, time_spent, notes, created_at
                ) VALUES (
                    :asset_id, :schedule_id, :maintenance_date, :maintenance_type, 
                    :performed_by, :cost, :time_spent, :notes, NOW()
                )
            ";
            
            $logStmt = $this->db->prepare($logQuery);
            $logStmt->bindParam(':asset_id', $data['asset_id'], PDO::PARAM_INT);
            $logStmt->bindParam(':schedule_id', $data['schedule_id'], PDO::PARAM_INT);
            $logStmt->bindParam(':maintenance_date', $data['maintenance_date'], PDO::PARAM_STR);
            $logStmt->bindParam(':maintenance_type', $data['maintenance_type'], PDO::PARAM_STR);
            $logStmt->bindParam(':performed_by', $data['performed_by'], PDO::PARAM_INT);
            $logStmt->bindParam(':cost', $data['cost'], PDO::PARAM_STR);
            $logStmt->bindParam(':time_spent', $data['time_spent'], PDO::PARAM_INT);
            $logStmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
            $logStmt->execute();
            
            // به‌روزرسانی تاریخ تعمیر و نگهداری بعدی
            $updateQuery = "
                UPDATE maintenance_schedules
                SET 
                    next_maintenance_date = DATE_ADD(:maintenance_date, INTERVAL frequency_days DAY),
                    updated_at = NOW()
                WHERE id = :schedule_id
            ";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':maintenance_date', $data['maintenance_date'], PDO::PARAM_STR);
            $updateStmt->bindParam(':schedule_id', $data['schedule_id'], PDO::PARAM_INT);
            $updateStmt->execute();
            
            // تایید تراکنش
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // بازگشت تراکنش در صورت خطا
            $this->db->rollBack();
            error_log("Error in logScheduledMaintenance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت برنامه‌های تعمیر و نگهداری ادواری یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array برنامه‌های تعمیر و نگهداری
     */
    public function getAssetMaintenanceSchedules($assetId) {
        try {
            $query = "
                SELECT 
                    ms.id,
                    ms.asset_id,
                    ms.maintenance_type_id,
                    mt.name AS maintenance_type_name,
                    ms.frequency_days,
                    ms.next_maintenance_date,
                    ms.technician_id,
                    u.fullname AS technician_name,
                    ms.notes,
                    ms.created_at,
                    ms.updated_at,
                    (
                        SELECT MAX(ml.maintenance_date)
                        FROM maintenance_logs ml
                        WHERE ml.schedule_id = ms.id
                    ) AS last_maintenance_date,
                    (
                        SELECT COUNT(*)
                        FROM maintenance_logs ml
                        WHERE ml.schedule_id = ms.id
                    ) AS maintenance_count
                FROM 
                    maintenance_schedules ms
                LEFT JOIN 
                    maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN 
                    users u ON ms.technician_id = u.id
                WHERE 
                    ms.asset_id = :asset_id
                ORDER BY 
                    ms.next_maintenance_date
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetMaintenanceSchedules: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت انواع تعمیر و نگهداری
     * 
     * @return array انواع تعمیر و نگهداری
     */
    public function getMaintenanceTypes() {
        try {
            $query = "
                SELECT 
                    id,
                    name,
                    description,
                    interval_days
                FROM 
                    maintenance_types
                ORDER BY 
                    name
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getMaintenanceTypes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت برنامه‌های تعمیر و نگهداری آینده
     * 
     * @param int $days تعداد روزهای آینده
     * @return array برنامه‌های تعمیر و نگهداری
     */
    public function getUpcomingMaintenanceSchedules($days = 30) {
        try {
            $query = "
                SELECT 
                    ms.id,
                    ms.asset_id,
                    a.name AS asset_name,
                    a.asset_tag,
                    ms.maintenance_type_id,
                    mt.name AS maintenance_type_name,
                    ms.frequency_days,
                    ms.next_maintenance_date,
                    DATEDIFF(ms.next_maintenance_date, CURDATE()) AS days_until_maintenance,
                    ms.technician_id,
                    u.fullname AS technician_name,
                    ms.notes,
                    (
                        SELECT u2.fullname
                        FROM users u2
                        JOIN asset_assignments aa ON u2.id = aa.user_id
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_to
                FROM 
                    maintenance_schedules ms
                JOIN 
                    assets a ON ms.asset_id = a.id
                LEFT JOIN 
                    maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN 
                    users u ON ms.technician_id = u.id
                WHERE 
                    ms.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                ORDER BY 
                    ms.next_maintenance_date
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUpcomingMaintenanceSchedules: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت برنامه‌های تعمیر و نگهداری معوق
     * 
     * @return array برنامه‌های تعمیر و نگهداری معوق
     */
    public function getOverdueMaintenanceSchedules() {
        try {
            $query = "
                SELECT 
                    ms.id,
                    ms.asset_id,
                    a.name AS asset_name,
                    a.asset_tag,
                    ms.maintenance_type_id,
                    mt.name AS maintenance_type_name,
                    ms.frequency_days,
                    ms.next_maintenance_date,
                    DATEDIFF(CURDATE(), ms.next_maintenance_date) AS days_overdue,
                    ms.technician_id,
                    u.fullname AS technician_name,
                    ms.notes,
                    (
                        SELECT u2.fullname
                        FROM users u2
                        JOIN asset_assignments aa ON u2.id = aa.user_id
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_to
                FROM 
                    maintenance_schedules ms
                JOIN 
                    assets a ON ms.asset_id = a.id
                LEFT JOIN 
                    maintenance_types mt ON ms.maintenance_type_id = mt.id
                LEFT JOIN 
                    users u ON ms.technician_id = u.id
                WHERE 
                    ms.next_maintenance_date < CURDATE()
                ORDER BY 
                    ms.next_maintenance_date
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getOverdueMaintenanceSchedules: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های قابل تخصیص (تخصیص داده نشده)
     * 
     * @param int|null $categoryId شناسه دسته‌بندی (اختیاری)
     * @return array لیست تجهیز‌های قابل تخصیص
     */
    public function getAssignableAssets($categoryId = null) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    m.name AS model_name,
                    c.name AS category_name
                FROM 
                    assets a
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    a.status = 'available'
                    AND NOT EXISTS (
                        SELECT 1 FROM asset_assignments aa 
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                    )
            ";
            
            $params = [];
            
            if ($categoryId !== null) {
                $query .= " AND m.category_id = :category_id";
                $params[':category_id'] = $categoryId;
            }
            
            $query .= " ORDER BY a.name";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssignableAssets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار تعمیر و نگهداری تجهیز‌ها
     * 
     * @return array آمار تعمیر و نگهداری
     */
    public function getMaintenanceStatistics() {
        try {
            $stats = [];
            
            // تعداد کل سوابق تعمیر و نگهداری
            $totalQuery = "SELECT COUNT(*) FROM maintenance_logs";
            $stmt = $this->db->query($totalQuery);
            $stats['total_logs'] = $stmt->fetchColumn();
            
            // میانگین هزینه تعمیر و نگهداری
            $avgCostQuery = "SELECT AVG(cost) FROM maintenance_logs WHERE cost IS NOT NULL";
            $stmt = $this->db->query($avgCostQuery);
            $stats['average_cost'] = $stmt->fetchColumn();
            
            // مجموع هزینه‌های تعمیر و نگهداری
            $totalCostQuery = "SELECT SUM(cost) FROM maintenance_logs WHERE cost IS NOT NULL";
            $stmt = $this->db->query($totalCostQuery);
            $stats['total_cost'] = $stmt->fetchColumn();
            
            // تعداد برنامه‌های تعمیر و نگهداری
            $schedulesQuery = "SELECT COUNT(*) FROM maintenance_schedules";
            $stmt = $this->db->query($schedulesQuery);
            $stats['total_schedules'] = $stmt->fetchColumn();
            
            // تعداد برنامه‌های تعمیر و نگهداری معوق
            $overdueQuery = "SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date < CURDATE()";
            $stmt = $this->db->query($overdueQuery);
            $stats['overdue_schedules'] = $stmt->fetchColumn();
            
            // تعداد برنامه‌های تعمیر و نگهداری آینده (30 روز)
            $upcomingQuery = "SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            $stmt = $this->db->query($upcomingQuery);
            $stats['upcoming_schedules'] = $stmt->fetchColumn();
            
            // تعداد تعمیر و نگهداری بر اساس نوع
            $byTypeQuery = "
                SELECT 
                    maintenance_type,
                    COUNT(*) as count
                FROM 
                    maintenance_logs
                GROUP BY 
                    maintenance_type
                ORDER BY 
                    count DESC
            ";
            $stmt = $this->db->query($byTypeQuery);
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in getMaintenanceStatistics: " . $e->getMessage());
            return [
                'total_logs' => 0,
                'average_cost' => 0,
                'total_cost' => 0,
                'total_schedules' => 0,
                'overdue_schedules' => 0,
                'upcoming_schedules' => 0,
                'by_type' => []
            ];
        }
    }

    /**
     * دریافت تجهیز‌های یک کاربر با اطلاعات کامل
     * 
     * @param int $userId شناسه کاربر
     * @return array لیست تجهیز‌های کاربر با اطلاعات کامل
     */
    public function getUserAssetsWithDetails($userId) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    a.status,
                    a.purchase_date,
                    a.warranty_months,
                    m.name AS model_name,
                    man.name AS manufacturer_name,
                    c.name AS category_name,
                    aa.assigned_at,
                    (
                        SELECT COUNT(*)
                        FROM maintenance_logs ml
                        WHERE ml.asset_id = a.id
                    ) AS maintenance_count,
                    (
                        SELECT MAX(ml.maintenance_date)
                        FROM maintenance_logs ml
                        WHERE ml.asset_id = a.id
                    ) AS last_maintenance_date,
                    (
                        SELECT COUNT(*)
                        FROM ticket_assets ta
                        JOIN tickets t ON ta.ticket_id = t.id
                        WHERE ta.asset_id = a.id
                    ) AS ticket_count,
                    (
                        SELECT COUNT(*)
                        FROM maintenance_schedules ms
                        WHERE ms.asset_id = a.id AND ms.next_maintenance_date < CURDATE()
                    ) AS overdue_maintenance_count
                FROM 
                    assets a
                JOIN 
                    asset_assignments aa ON a.id = aa.asset_id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    manufacturers man ON m.manufacturer_id = man.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                WHERE 
                    aa.user_id = :user_id AND aa.is_current = 1
                ORDER BY 
                    aa.assigned_at DESC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // محاسبه اطلاعات گارانتی برای هر تجهیز
            foreach ($assets as &$asset) {
                if (!empty($asset['purchase_date']) && !empty($asset['warranty_months'])) {
                    $purchaseDate = new DateTime($asset['purchase_date']);
                    $warrantyEnd = clone $purchaseDate;
                    $warrantyEnd->modify("+{$asset['warranty_months']} months");
                    
                    $now = new DateTime();
                    $asset['warranty_end_date'] = $warrantyEnd->format('Y-m-d');
                    $asset['is_in_warranty'] = ($now <= $warrantyEnd);
                    
                    if ($asset['is_in_warranty']) {
                        $interval = $now->diff($warrantyEnd);
                        $asset['warranty_remaining'] = [
                            'days' => $interval->days,
                            'formatted' => $interval->format('%y سال، %m ماه و %d روز')
                        ];
                    } else {
                        $asset['warranty_remaining'] = [
                            'days' => 0,
                            'formatted' => 'منقضی شده'
                        ];
                    }
                } else {
                    $asset['warranty_end_date'] = null;
                    $asset['is_in_warranty'] = false;
                    $asset['warranty_remaining'] = [
                        'days' => 0,
                        'formatted' => 'نامشخص'
                    ];
                }
                
                // دریافت مشخصات سخت‌افزاری
                $specsQuery = "
                    SELECT spec_name, spec_value
                    FROM asset_specifications
                    WHERE asset_id = :asset_id
                    ORDER BY spec_name
                ";
                
                $specsStmt = $this->db->prepare($specsQuery);
                $specsStmt->bindParam(':asset_id', $asset['id'], PDO::PARAM_INT);
                $specsStmt->execute();
                
                $asset['specifications'] = $specsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $assets;
        } catch (PDOException $e) {
            error_log("Error in getUserAssetsWithDetails: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار تجهیز‌های کاربران بر اساس بخش
     * 
     * @return array آمار تجهیز‌های کاربران بر اساس بخش
     */
    public function getAssetStatsByDepartment() {
        try {
            $query = "
                SELECT 
                    d.id,
                    d.name AS department_name,
                    COUNT(DISTINCT a.id) AS asset_count,
                    SUM(a.purchase_cost) AS total_value,
                    COUNT(DISTINCT u.id) AS user_count,
                    ROUND(COUNT(DISTINCT a.id) / COUNT(DISTINCT u.id), 2) AS assets_per_user
                FROM 
                    departments d
                JOIN 
                    users u ON u.department_id = d.id
                LEFT JOIN 
                    asset_assignments aa ON aa.user_id = u.id AND aa.is_current = 1
                LEFT JOIN 
                    assets a ON aa.asset_id = a.id
                GROUP BY 
                    d.id, d.name
                ORDER BY 
                    asset_count DESC
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetStatsByDepartment: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت کاربران با بیشترین تعداد تجهیز
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست کاربران
     */
    public function getUsersWithMostAssets($limit = 10) {
        try {
            $query = "
                SELECT 
                    u.id,
                    u.fullname,
                    u.employee_number,
                    d.name AS department_name,
                    COUNT(aa.asset_id) AS asset_count,
                    SUM(a.purchase_cost) AS total_asset_value
                FROM 
                    users u
                JOIN 
                    asset_assignments aa ON u.id = aa.user_id AND aa.is_current = 1
                JOIN 
                    assets a ON aa.asset_id = a.id
                LEFT JOIN 
                    departments d ON u.department_id = d.id
                GROUP BY 
                    u.id, u.fullname, u.employee_number, d.name
                ORDER BY 
                    asset_count DESC
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUsersWithMostAssets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌های با بیشترین تعداد درخواست کار
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست تجهیز‌ها
     */
    public function getAssetsWithMostTickets($limit = 10) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.asset_tag,
                    m.name AS model_name,
                    c.name AS category_name,
                    COUNT(ta.ticket_id) AS ticket_count,
                    (
                        SELECT u.fullname
                        FROM users u
                        JOIN asset_assignments aa ON u.id = aa.user_id
                        WHERE aa.asset_id = a.id AND aa.is_current = 1
                        LIMIT 1
                    ) AS assigned_to
                FROM 
                    assets a
                JOIN 
                    ticket_assets ta ON a.id = ta.asset_id
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                GROUP BY 
                    a.id, a.name, a.asset_tag, m.name, c.name
                ORDER BY 
                    ticket_count DESC
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetsWithMostTickets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ایجاد یک دسته‌بندی تجهیز جدید
     * 
     * @param array $data اطلاعات دسته‌بندی
     * @return int|bool شناسه دسته‌بندی جدید یا false در صورت خطا
     */
    public function createCategory($data) {
        try {
            $query = "
                INSERT INTO asset_categories (
                    name, description, created_at, updated_at
                ) VALUES (
                    :name, :description, NOW(), NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
            $stmt->execute();
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error in createCategory: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ایجاد یک مدل تجهیز جدید
     * 
     * @param array $data اطلاعات مدل
     * @return int|bool شناسه مدل جدید یا false در صورت خطا
     */
    public function createModel($data) {
        try {
            $query = "
                INSERT INTO asset_models (
                    name, model_number, manufacturer_id, category_id, 
                    description, created_at, updated_at
                ) VALUES (
                    :name, :model_number, :manufacturer_id, :category_id, 
                    :description, NOW(), NOW()
                )
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':model_number', $data['model_number'], PDO::PARAM_STR);
            $stmt->bindParam(':manufacturer_id', $data['manufacturer_id'], PDO::PARAM_INT);
            $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
            $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
            $stmt->execute();
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error in createModel: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت آمار تجهیز‌ها برای داشبورد
     * 
     * @return array آمار تجهیز‌ها برای داشبورد
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // تعداد کل تجهیز‌ها
            $totalQuery = "SELECT COUNT(*) FROM assets";
            $stmt = $this->db->query($totalQuery);
            $stats['total_assets'] = $stmt->fetchColumn();
            
            // تعداد تجهیز‌های تخصیص داده شده
            $assignedQuery = "
                SELECT COUNT(*) FROM assets a
                WHERE EXISTS (
                    SELECT 1 FROM asset_assignments aa 
                    WHERE aa.asset_id = a.id AND aa.is_current = 1
                )
            ";
            $stmt = $this->db->query($assignedQuery);
            $stats['assigned_assets'] = $stmt->fetchColumn();
            
            // تعداد تجهیز‌های تخصیص داده نشده
            $stats['unassigned_assets'] = $stats['total_assets'] - $stats['assigned_assets'];
            
            // تعداد تجهیز‌های در گارانتی
            $inWarrantyQuery = "
                SELECT COUNT(*) FROM assets
                WHERE DATE_ADD(purchase_date, INTERVAL warranty_months MONTH) >= CURDATE()
                AND purchase_date IS NOT NULL AND warranty_months IS NOT NULL
            ";
            $stmt = $this->db->query($inWarrantyQuery);
            $stats['in_warranty'] = $stmt->fetchColumn();
            
            // تعداد تجهیز‌های خارج از گارانتی
            $outOfWarrantyQuery = "
                SELECT COUNT(*) FROM assets
                WHERE DATE_ADD(purchase_date, INTERVAL warranty_months MONTH) < CURDATE()
                AND purchase_date IS NOT NULL AND warranty_months IS NOT NULL
            ";
            $stmt = $this->db->query($outOfWarrantyQuery);
            $stats['out_of_warranty'] = $stmt->fetchColumn();
            
            // تعداد تجهیز‌های نزدیک به پایان گارانتی (30 روز)
            $nearingWarrantyEndQuery = "
                SELECT COUNT(*) FROM assets
                WHERE DATEDIFF(DATE_ADD(purchase_date, INTERVAL warranty_months MONTH), CURDATE()) BETWEEN 0 AND 30
                AND purchase_date IS NOT NULL AND warranty_months IS NOT NULL
            ";
            $stmt = $this->db->query($nearingWarrantyEndQuery);
            $stats['nearing_warranty_end'] = $stmt->fetchColumn();
            
            // تعداد برنامه‌های تعمیر و نگهداری معوق
            $overdueMaintenanceQuery = "SELECT COUNT(*) FROM maintenance_schedules WHERE next_maintenance_date < CURDATE()";
            $stmt = $this->db->query($overdueMaintenanceQuery);
            $stats['overdue_maintenance'] = $stmt->fetchColumn();
            
            // تعداد برنامه‌های تعمیر و نگهداری آینده (7 روز)
            $upcomingMaintenanceQuery = "
                SELECT COUNT(*) FROM maintenance_schedules 
                WHERE next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ";
            $stmt = $this->db->query($upcomingMaintenanceQuery);
            $stats['upcoming_maintenance'] = $stmt->fetchColumn();
            
            // تعداد تجهیز‌ها بر اساس دسته‌بندی (5 دسته‌بندی برتر)
            $topCategoriesQuery = "
                SELECT c.name, COUNT(a.id) as count
                FROM assets a
                JOIN asset_models m ON a.model_id = m.id
                JOIN asset_categories c ON m.category_id = c.id
                GROUP BY c.name
                ORDER BY count DESC
                LIMIT 5
            ";
            $stmt = $this->db->query($topCategoriesQuery);
            $stats['top_categories'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // ارزش کل تجهیز‌ها
            $totalValueQuery = "SELECT SUM(purchase_cost) as total_value FROM assets WHERE purchase_cost IS NOT NULL";
            $stmt = $this->db->query($totalValueQuery);
            $stats['total_value'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in getDashboardStats: " . $e->getMessage());
            return [
                'total_assets' => 0,
                'assigned_assets' => 0,
                'unassigned_assets' => 0,
                'in_warranty' => 0,
                'out_of_warranty' => 0,
                'nearing_warranty_end' => 0,
                'overdue_maintenance' => 0,
                'upcoming_maintenance' => 0,
                'top_categories' => [],
                'total_value' => 0
            ];
        }
    }

    /**
     * دریافت آمار تجهیز‌ها بر اساس سال خرید
     * 
     * @return array آمار تجهیز‌ها بر اساس سال خرید
     */
    public function getAssetStatsByPurchaseYear() {
        try {
            $query = "
                SELECT 
                    YEAR(purchase_date) as year,
                    COUNT(*) as count,
                    SUM(purchase_cost) as total_cost
                FROM 
                    assets
                WHERE 
                    purchase_date IS NOT NULL
                GROUP BY 
                    YEAR(purchase_date)
                ORDER BY 
                    year
            ";
            
            $stmt = $this->db->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAssetStatsByPurchaseYear: " . $e->getMessage());
            return [];
        }
    }

    /**
     * بررسی وجود یک تجهیز با شماره سریال یا برچسب تجهیز
     * 
     * @param string $serialNumber شماره سریال
     * @param string $assetTag برچسب تجهیز
     * @param int|null $excludeId شناسه تجهیز برای مستثنی کردن (برای به‌روزرسانی)
     * @return bool آیا تجهیز وجود دارد
     */
    public function checkAssetExists($serialNumber, $assetTag, $excludeId = null) {
        try {
            $query = "
                SELECT COUNT(*) FROM assets
                WHERE (serial_number = :serial_number OR asset_tag = :asset_tag)
            ";
            
            $params = [
                ':serial_number' => $serialNumber,
                ':asset_tag' => $assetTag
            ];
            
            if ($excludeId !== null) {
                $query .= " AND id != :exclude_id";
                $params[':exclude_id'] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                if ($key === ':exclude_id') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }
            
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in checkAssetExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ایجاد یک برچسب تجهیز جدید بر اساس الگو
     * 
     * @param string $prefix پیشوند برچسب تجهیز
     * @return string برچسب تجهیز جدید
     */
    public function generateAssetTag($prefix = 'ASSET') {
        try {
            // دریافت آخرین شماره برچسب تجهیز
            $query = "
                SELECT asset_tag FROM assets
                WHERE asset_tag LIKE :prefix
                ORDER BY CAST(SUBSTRING(asset_tag, LENGTH(:prefix) + 1) AS UNSIGNED) DESC
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':prefix', $prefix, PDO::PARAM_STR);
            $stmt->execute();
            
            $lastTag = $stmt->fetchColumn();
            
            if ($lastTag) {
                // استخراج شماره از آخرین برچسب
                $lastNumber = (int)substr($lastTag, strlen($prefix));
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }
            
            // ایجاد برچسب جدید
            $newTag = $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
            
            return $newTag;
        } catch (PDOException $e) {
            error_log("Error in generateAssetTag: " . $e->getMessage());
            return $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        }
    }

    /**
     * دریافت تاریخچه کامل یک تجهیز
     * 
     * @param int $assetId شناسه تجهیز
     * @return array تاریخچه تجهیز
     */
    public function getAssetHistory($assetId) {
        try {
            $history = [];
            
            // تاریخچه تخصیص
            $assignmentQuery = "
                SELECT 
                    'assignment' AS event_type,
                    aa.assigned_at AS event_date,
                    CASE 
                        WHEN aa.is_current = 1 THEN 'تخصیص به کاربر'
                        ELSE 'بازگشت از کاربر'
                    END AS event_name,
                    CONCAT(
                        'کاربر: ', u.fullname, 
                        CASE 
                            WHEN aa.is_current = 0 AND aa.returned_at IS NOT NULL 
                            THEN CONCAT(' (از ', DATE_FORMAT(aa.assigned_at, '%Y-%m-%d'), ' تا ', DATE_FORMAT(aa.returned_at, '%Y-%m-%d'), ')')
                            ELSE CONCAT(' (از ', DATE_FORMAT(aa.assigned_at, '%Y-%m-%d'), ')')
                        END
                    ) AS event_description,
                    aa.notes,
                    u.fullname AS user_name
                FROM 
                    asset_assignments aa
                LEFT JOIN 
                    users u ON aa.user_id = u.id
                WHERE 
                    aa.asset_id = :asset_id
            ";
            
            $assignmentStmt = $this->db->prepare($assignmentQuery);
            $assignmentStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $assignmentStmt->execute();
            
            $history = array_merge($history, $assignmentStmt->fetchAll(PDO::FETCH_ASSOC));
            
            // تاریخچه تعمیر و نگهداری
            $maintenanceQuery = "
                SELECT 
                    'maintenance' AS event_type,
                    ml.maintenance_date AS event_date,
                    CONCAT('تعمیر و نگهداری: ', ml.maintenance_type) AS event_name,
                    CASE 
                        WHEN ml.cost IS NOT NULL THEN CONCAT('هزینه: ', ml.cost, ' | ', ml.notes)
                        ELSE ml.notes
                    END AS event_description,
                    ml.notes,
                    u.fullname AS user_name
                FROM 
                    maintenance_logs ml
                LEFT JOIN 
                    users u ON ml.performed_by = u.id
                WHERE 
                    ml.asset_id = :asset_id
            ";
            
            $maintenanceStmt = $this->db->prepare($maintenanceQuery);
            $maintenanceStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $maintenanceStmt->execute();
            
            $history = array_merge($history, $maintenanceStmt->fetchAll(PDO::FETCH_ASSOC));
            
            // تاریخچه درخواست‌های کار
            $ticketQuery = "
                SELECT 
                    'ticket' AS event_type,
                    t.created_at AS event_date,
                    CONCAT('درخواست کار: ', t.title) AS event_name,
                    CONCAT('وضعیت: ', t.status, ' | اولویت: ', t.priority) AS event_description,
                    t.description AS notes,
                    t.employee_name AS user_name
                FROM 
                    tickets t
                JOIN 
                    ticket_assets ta ON t.id = ta.ticket_id
                WHERE 
                    ta.asset_id = :asset_id
            ";
            
            $ticketStmt = $this->db->prepare($ticketQuery);
            $ticketStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $ticketStmt->execute();
            
            $history = array_merge($history, $ticketStmt->fetchAll(PDO::FETCH_ASSOC));
            
            // تاریخچه تغییرات وضعیت
            $statusQuery = "
                SELECT 
                    'status_change' AS event_type,
                    updated_at AS event_date,
                    CONCAT('تغییر وضعیت به: ', status) AS event_name,
                    notes AS event_description,
                    notes,
                    'سیستم' AS user_name
                FROM 
                    assets
                WHERE 
                    id = :asset_id
            ";
            
            $statusStmt = $this->db->prepare($statusQuery);
            $statusStmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
            $statusStmt->execute();
            
            $history = array_merge($history, $statusStmt->fetchAll(PDO::FETCH_ASSOC));
            
            // مرتب‌سازی بر اساس تاریخ (نزولی)
            usort($history, function($a, $b) {
                return strtotime($b['event_date']) - strtotime($a['event_date']);
            });
            
            return $history;
        } catch (PDOException $e) {
            error_log("Error in getAssetHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار تجهیز‌ها
     * 
     * @return array آمار تجهیز‌ها
     */
    public function getAssetStats() {
        try {
            $stats = [
                'total' => 0,
                'assigned' => 0,
                'unassigned' => 0,
                'active' => 0,
                'inactive' => 0,
                'maintenance' => 0,
                'by_type' => [],
                'by_status' => [],
                'by_location' => []
            ];
            
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                
                // تعداد کل تجهیز‌ها
                $stmt = $this->db->query("SELECT COUNT(*) as count FROM assets");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['total'] = (int)$result['count'];
                
                // تعداد تجهیز‌های تخصیص داده شده
                $stmt = $this->db->query("
                    SELECT COUNT(DISTINCT a.id) as count 
                    FROM assets a 
                    JOIN asset_assignments aa ON a.id = aa.asset_id 
                    WHERE aa.is_current = 1
                ");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['assigned'] = (int)$result['count'];
                
                // تعداد تجهیز‌های تخصیص داده نشده
                $stats['unassigned'] = $stats['total'] - $stats['assigned'];
                
                // تعداد تجهیز‌های فعال
                $stmt = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE status = 'active'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['active'] = (int)$result['count'];
                
                // تعداد تجهیز‌های غیرفعال
                $stmt = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE status = 'inactive'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['inactive'] = (int)$result['count'];
                
                // تعداد تجهیز‌های در حال تعمیر
                $stmt = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE status = 'maintenance'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['maintenance'] = (int)$result['count'];
                
                // آمار بر اساس نوع تجهیز
                $stmt = $this->db->query("
                    SELECT asset_type, COUNT(*) as count 
                    FROM assets 
                    GROUP BY asset_type 
                    ORDER BY count DESC
                ");
                $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // آمار بر اساس وضعیت
                $stmt = $this->db->query("
                    SELECT status, COUNT(*) as count 
                    FROM assets 
                    GROUP BY status 
                    ORDER BY count DESC
                ");
                $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // آمار بر اساس مکان
                $stmt = $this->db->query("
                    SELECT location, COUNT(*) as count 
                    FROM assets 
                    WHERE location IS NOT NULL AND location != ''
                    GROUP BY location 
                    ORDER BY count DESC
                ");
                $stats['by_location'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                
                // تعداد کل تجهیز‌ها
                $result = $this->db->query("SELECT COUNT(*) as count FROM assets");
                $row = $result->fetch_assoc();
                $stats['total'] = (int)$row['count'];
                
                // تعداد تجهیز‌های تخصیص داده شده
                $result = $this->db->query("
                    SELECT COUNT(DISTINCT a.id) as count 
                    FROM assets a 
                    JOIN asset_assignments aa ON a.id = aa.asset_id 
                    WHERE aa.is_current = 1
                ");
                $row = $result->fetch_assoc();
                $stats['assigned'] = (int)$row['count'];
                
                // تعداد تجهیز‌های تخصیص داده نشده
                $stats['unassigned'] = $stats['total'] - $stats['assigned'];
                
                // تعداد تجهیز‌های فعال
                $result = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE status = 'active'");
                $row = $result->fetch_assoc();
                $stats['active'] = (int)$row['count'];
                
                // تعداد تجهیز‌های غیرفعال
                $result = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE status = 'inactive'");
                $row = $result->fetch_assoc();
                $stats['inactive'] = (int)$row['count'];
                
                // تعداد تجهیز‌های در حال تعمیر
                $result = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE status = 'maintenance'");
                $row = $result->fetch_assoc();
                $stats['maintenance'] = (int)$row['count'];
                
                // آمار بر اساس نوع تجهیز
                $result = $this->db->query("
                    SELECT asset_type, COUNT(*) as count 
                    FROM assets 
                    GROUP BY asset_type 
                    ORDER BY count DESC
                ");
                $stats['by_type'] = [];
                while ($row = $result->fetch_assoc()) {
                    $stats['by_type'][] = $row;
                }
                
                // آمار بر اساس وضعیت
                $result = $this->db->query("
                    SELECT status, COUNT(*) as count 
                    FROM assets 
                    GROUP BY status 
                    ORDER BY count DESC
                ");
                $stats['by_status'] = [];
                while ($row = $result->fetch_assoc()) {
                    $stats['by_status'][] = $row;
                }
                
                // آمار بر اساس مکان
                $result = $this->db->query("
                    SELECT location, COUNT(*) as count 
                    FROM assets 
                    WHERE location IS NOT NULL AND location != ''
                    GROUP BY location 
                    ORDER BY count DESC
                ");
                $stats['by_location'] = [];
                while ($row = $result->fetch_assoc()) {
                    $stats['by_location'][] = $row;
                }
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getAssetStats: " . $e->getMessage());
            return [
                'total' => 0,
                'assigned' => 0,
                'unassigned' => 0,
                'active' => 0,
                'inactive' => 0,
                'maintenance' => 0,
                'by_type' => [],
                'by_status' => [],
                'by_location' => []
            ];
        }
    }

    /**
     * دریافت توزیع تجهیز‌ها بر اساس دسته‌بندی
     * 
     * @return array توزیع تجهیز‌ها بر اساس دسته‌بندی
     */
    public function getAssetCategoryDistribution() {
        try {
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        ac.name as category_name,
                        COUNT(a.id) as count
                    FROM 
                        asset_categories ac
                    LEFT JOIN 
                        assets a ON ac.id = a.category_id
                    GROUP BY 
                        ac.id, ac.name
                    ORDER BY 
                        count DESC
                ";
                
                $stmt = $this->db->query($query);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        ac.name as category_name,
                        COUNT(a.id) as count
                    FROM 
                        asset_categories ac
                    LEFT JOIN 
                        assets a ON ac.id = a.category_id
                    GROUP BY 
                        ac.id, ac.name
                    ORDER BY 
                        count DESC
                ";
                
                $result = $this->db->query($query);
                
                if (!$result) {
                    throw new Exception("خطا در اجرای کوئری: " . $this->db->error);
                }
                
                $distribution = [];
                while ($row = $result->fetch_assoc()) {
                    $distribution[] = $row;
                }
                
                return $distribution;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getAssetCategoryDistribution: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌هایی که نیاز به توجه دارند
     * 
     * @param int $limit محدودیت تعداد نتایج (اختیاری)
     * @return array لیست تجهیز‌هایی که نیاز به توجه دارند
     */
    public function getAssetsNeedingAttention($limit = 10) {
        try {
            $today = date('Y-m-d');
            $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
            
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        a.id,
                        a.asset_tag,
                        a.name,
                        a.serial_number,
                        a.asset_type,
                        a.status,
                        a.warranty_expiry_date,
                        a.last_maintenance_date,
                        a.next_maintenance_date,
                        u.fullname as assigned_to_name,
                        CASE
                            WHEN a.status = 'maintenance' THEN 'در حال تعمیر'
                            WHEN a.warranty_expiry_date BETWEEN :today AND :thirtyDaysLater THEN 'گارانتی در حال اتمام'
                            WHEN a.next_maintenance_date <= :today THEN 'نیاز به تعمیر و نگهداری'
                            ELSE 'نیاز به بررسی'
                        END as attention_reason
                    FROM 
                        assets a
                    LEFT JOIN 
                        asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN 
                        users u ON aa.user_id = u.id
                    WHERE 
                        a.status = 'maintenance' OR
                        (a.warranty_expiry_date BETWEEN :today AND :thirtyDaysLater) OR
                        (a.next_maintenance_date <= :today)
                    ORDER BY 
                        CASE
                            WHEN a.status = 'maintenance' THEN 1
                            WHEN a.next_maintenance_date <= :today THEN 2
                            WHEN a.warranty_expiry_date BETWEEN :today AND :thirtyDaysLater THEN 3
                            ELSE 4
                        END
                    LIMIT :limit
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':today', $today, PDO::PARAM_STR);
                $stmt->bindParam(':thirtyDaysLater', $thirtyDaysLater, PDO::PARAM_STR);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        a.id,
                        a.asset_tag,
                        a.name,
                        a.serial_number,
                        a.asset_type,
                        a.status,
                        a.warranty_expiry_date,
                        a.last_maintenance_date,
                        a.next_maintenance_date,
                        u.fullname as assigned_to_name,
                        CASE
                            WHEN a.status = 'maintenance' THEN 'در حال تعمیر'
                            WHEN a.warranty_expiry_date BETWEEN ? AND ? THEN 'گارانتی در حال اتمام'
                            WHEN a.next_maintenance_date <= ? THEN 'نیاز به تعمیر و نگهداری'
                            ELSE 'نیاز به بررسی'
                        END as attention_reason
                    FROM 
                        assets a
                    LEFT JOIN 
                        asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN 
                        users u ON aa.user_id = u.id
                    WHERE 
                        a.status = 'maintenance' OR
                        (a.warranty_expiry_date BETWEEN ? AND ?) OR
                        (a.next_maintenance_date <= ?)
                    ORDER BY 
                        CASE
                            WHEN a.status = 'maintenance' THEN 1
                            WHEN a.next_maintenance_date <= ? THEN 2
                            WHEN a.warranty_expiry_date BETWEEN ? AND ? THEN 3
                            ELSE 4
                        END
                    LIMIT ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("ssssssssi", $today, $thirtyDaysLater, $today, $today, $thirtyDaysLater, $today, $today, $today, $thirtyDaysLater, $limit);
                $stmt->execute();
                
                $result = $stmt->get_result();
                return $result->fetch_all(MYSQLI_ASSOC);
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getAssetsNeedingAttention: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار تجهیز‌ها بر اساس وضعیت
     * 
     * @return array آمار تجهیز‌ها بر اساس وضعیت
     */
    public function getAssetsByStatus() {
        try {
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM 
                        assets
                    GROUP BY 
                        status
                    ORDER BY 
                        count DESC
                ";
                
                $stmt = $this->db->query($query);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // تبدیل نتایج به آرایه با کلید وضعیت
                $statusCounts = [];
                foreach ($results as $row) {
                    $statusCounts[$row['status']] = (int)$row['count'];
                }
                
                // اطمینان از وجود تمام وضعیت‌های رایج در آرایه نتایج
                $commonStatuses = ['active', 'inactive', 'maintenance', 'disposed', 'lost', 'stolen'];
                foreach ($commonStatuses as $status) {
                    if (!isset($statusCounts[$status])) {
                        $statusCounts[$status] = 0;
                    }
                }
                
                // تبدیل به فرمت مناسب برای نمودار
                $formattedResults = [];
                foreach ($statusCounts as $status => $count) {
                    $formattedResults[] = [
                        'status' => $status,
                        'count' => $count,
                        'label' => $this->translateStatus($status)
                    ];
                }
                
                return $formattedResults;
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM 
                        assets
                    GROUP BY 
                        status
                    ORDER BY 
                        count DESC
                ";
                
                $result = $this->db->query($query);
                
                if (!$result) {
                    throw new Exception("خطا در اجرای کوئری: " . $this->db->error);
                }
                
                // تبدیل نتایج به آرایه با کلید وضعیت
                $statusCounts = [];
                while ($row = $result->fetch_assoc()) {
                    $statusCounts[$row['status']] = (int)$row['count'];
                }
                
                // اطمینان از وجود تمام وضعیت‌های رایج در آرایه نتایج
                $commonStatuses = ['active', 'inactive', 'maintenance', 'disposed', 'lost', 'stolen'];
                foreach ($commonStatuses as $status) {
                    if (!isset($statusCounts[$status])) {
                        $statusCounts[$status] = 0;
                    }
                }
                
                // تبدیل به فرمت مناسب برای نمودار
                $formattedResults = [];
                foreach ($statusCounts as $status => $count) {
                    $formattedResults[] = [
                        'status' => $status,
                        'count' => $count,
                        'label' => $this->translateStatus($status)
                    ];
                }
                
                return $formattedResults;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getAssetsByStatus: " . $e->getMessage());
            return [];
        }
    }

    // اضافه کردن این تابع به کلاس Asset
    public function translateStatus($status) {
        $translations = [
            'available' => 'در دسترس',
            'assigned' => 'تخصیص داده شده',
            'maintenance' => 'در تعمیرات',
            'retired' => 'بازنشسته',
            'lost' => 'گم شده',
            'broken' => 'خراب',
            '' => 'نامشخص',
            null => 'نامشخص'
        ];
        
        return $translations[$status] ?? $status;
    }

    /**
     * دریافت آمار تجهیز‌ها بر اساس دپارتمان
     * 
     * @param int $limit محدودیت تعداد نتایج (اختیاری)
     * @return array آمار تجهیز‌ها بر اساس دپارتمان
     */
    public function getAssetsByDepartment($limit = 10) {
        try {
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        d.name as department_name,
                        COUNT(a.id) as count
                    FROM 
                        departments d
                    LEFT JOIN 
                        users u ON u.department_id = d.id
                    LEFT JOIN 
                        asset_assignments aa ON aa.user_id = u.id AND aa.is_current = 1
                    LEFT JOIN 
                        assets a ON a.id = aa.asset_id
                    GROUP BY 
                        d.id, d.name
                    ORDER BY 
                        count DESC
                    LIMIT :limit
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        d.name as department_name,
                        COUNT(a.id) as count
                    FROM 
                        departments d
                    LEFT JOIN 
                        users u ON u.department_id = d.id
                    LEFT JOIN 
                        asset_assignments aa ON aa.user_id = u.id AND aa.is_current = 1
                    LEFT JOIN 
                        assets a ON a.id = aa.asset_id
                    GROUP BY 
                        d.id, d.name
                    ORDER BY 
                        count DESC
                    LIMIT ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                
                $result = $stmt->get_result();
                return $result->fetch_all(MYSQLI_ASSOC);
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getAssetsByDepartment: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌هایی که گارانتی آنها در حال اتمام است
     * 
     * @param int $days تعداد روزهای آینده برای بررسی (پیش‌فرض: 90)
     * @param int $limit محدودیت تعداد نتایج (اختیاری)
     * @return array لیست تجهیز‌هایی که گارانتی آنها در حال اتمام است
     */
    public function getAssetsWithExpiringWarranty($days = 90, $limit = 10) {
        try {
            $today = date('Y-m-d');
            $futureDate = date('Y-m-d', strtotime("+$days days"));
            
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        a.id,
                        a.asset_tag,
                        a.name,
                        a.serial_number,
                        a.asset_type,
                        a.model,
                        a.warranty_expiry_date,
                        DATEDIFF(a.warranty_expiry_date, :today) as days_remaining,
                        u.fullname as assigned_to_name,
                        u.id as assigned_to_id
                    FROM 
                        assets a
                    LEFT JOIN 
                        asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN 
                        users u ON aa.user_id = u.id
                    WHERE 
                        a.warranty_expiry_date BETWEEN :today AND :futureDate
                    ORDER BY 
                        a.warranty_expiry_date ASC
                    LIMIT :limit
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':today', $today, PDO::PARAM_STR);
                $stmt->bindParam(':futureDate', $futureDate, PDO::PARAM_STR);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        a.id,
                        a.asset_tag,
                        a.name,
                        a.serial_number,
                        a.asset_type,
                        a.model,
                        a.warranty_expiry_date,
                        DATEDIFF(a.warranty_expiry_date, ?) as days_remaining,
                        u.fullname as assigned_to_name,
                        u.id as assigned_to_id
                    FROM 
                        assets a
                    LEFT JOIN 
                        asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN 
                        users u ON aa.user_id = u.id
                    WHERE 
                        a.warranty_expiry_date BETWEEN ? AND ?
                    ORDER BY 
                        a.warranty_expiry_date ASC
                    LIMIT ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("sssi", $today, $today, $futureDate, $limit);
                $stmt->execute();
                
                $result = $stmt->get_result();
                return $result->fetch_all(MYSQLI_ASSOC);
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getAssetsWithExpiringWarranty: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تجهیز‌هایی که اخیراً اضافه شده‌اند
     * 
     * @param int $days تعداد روزهای اخیر (پیش‌فرض: 30)
     * @param int $limit محدودیت تعداد نتایج (پیش‌فرض: 10)
     * @return array لیست تجهیز‌هایی که اخیراً اضافه شده‌اند
     */
    public function getRecentlyAddedAssets($days = 30, $limit = 10) {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-$days days"));
            
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "
                    SELECT 
                        a.id,
                        a.asset_tag,
                        a.name,
                        a.serial_number,
                        a.asset_type,
                        a.model,
                        a.purchase_date,
                        a.purchase_cost,
                        a.status,
                        a.created_at,
                        u.fullname as assigned_to_name,
                        u.id as assigned_to_id
                    FROM 
                        assets a
                    LEFT JOIN 
                        asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN 
                        users u ON aa.user_id = u.id
                    WHERE 
                        a.created_at >= :cutoffDate
                    ORDER BY 
                        a.created_at DESC
                    LIMIT :limit
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':cutoffDate', $cutoffDate, PDO::PARAM_STR);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "
                    SELECT 
                        a.id,
                        a.asset_tag,
                        a.name,
                        a.serial_number,
                        a.asset_type,
                        a.model,
                        a.purchase_date,
                        a.purchase_cost,
                        a.status,
                        a.created_at,
                        u.fullname as assigned_to_name,
                        u.id as assigned_to_id
                    FROM 
                        assets a
                    LEFT JOIN 
                        asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                    LEFT JOIN 
                        users u ON aa.user_id = u.id
                    WHERE 
                        a.created_at >= ?
                    ORDER BY 
                        a.created_at DESC
                    LIMIT ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("si", $cutoffDate, $limit);
                $stmt->execute();
                
                $result = $stmt->get_result();
                return $result->fetch_all(MYSQLI_ASSOC);
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getRecentlyAddedAssets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تمام تجهیز‌ها با قابلیت صفحه‌بندی و مرتب‌سازی
     * 
     * @param array $filters فیلترهای جستجو
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array تجهیز‌ها و اطلاعات صفحه‌بندی
     */
    public function getAllAssets($filters = [], $sortBy = 'id', $order = 'desc', $page = 1, $perPage = 10) {
        try {
            // تبدیل $page و $perPage به عدد صحیح برای جلوگیری از خطای تفریق رشته از عدد
            $page = (int)$page;
            $perPage = (int)$perPage;
            
            $whereConditions = [];
            $params = [];
            
            // اعمال فیلترها
            if (!empty($filters['query'])) {
                $search = "%" . $filters['query'] . "%";
                $whereConditions[] = "(a.name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ? OR a.notes LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
            
            if (!empty($filters['category_id'])) {
                $whereConditions[] = "a.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            if (!empty($filters['model_id'])) {
                $whereConditions[] = "a.model_id = ?";
                $params[] = $filters['model_id'];
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "a.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['serial_number'])) {
                $whereConditions[] = "a.serial_number LIKE ?";
                $params[] = "%" . $filters['serial_number'] . "%";
            }
            
            if (!empty($filters['purchase_date'])) {
                $whereConditions[] = "DATE(a.purchase_date) = ?";
                $params[] = $filters['purchase_date'];
            }

            // جستجو بر اساس شماره پرسنلی
            if (isset($filters['employee_number']) && !empty($filters['employee_number'])) {
                $whereConditions[] = "u.employee_number LIKE :employee_number";
                $params[':employee_number'] = '%' . $filters['employee_number'] . '%';
            }
            
            // فیلتر بر اساس کاربر (برای کاربران غیر ادمین)
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "aa.user_id = ? AND aa.is_current = 1";
                $params[] = $filters['user_id'];
            }
            
            // ساخت بخش WHERE کوئری
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // تعیین ترتیب مرتب‌سازی
            $validSortColumns = ['id', 'name', 'asset_tag', 'serial_number', 'status', 'category_name', 'model_name', 'purchase_date', 'assigned_to'];
            $sortBy = in_array($sortBy, $validSortColumns) ? $sortBy : 'id';
            $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
            
            // تنظیم ستون مرتب‌سازی با توجه به نام ستون
            $sortColumn = 'a.id'; // پیش‌فرض
            switch ($sortBy) {
                case 'category_name':
                    $sortColumn = 'ac.name';
                    break;
                case 'model_name':
                    $sortColumn = 'am.name';
                    break;
                case 'assigned_to':
                    $sortColumn = 'u.fullname';
                    break;
                default:
                    $sortColumn = "a.$sortBy";
                    break;
            }
            
            // محاسبه offset برای صفحه‌بندی
            $offset = ($page - 1) * $perPage;
            
            // کوئری اصلی برای دریافت تجهیز‌ها
            $query = "
                SELECT a.*, ac.name as category_name, am.name as model_name, 
                    u.fullname as assigned_to
                FROM assets a
                LEFT JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN asset_models am ON a.model_id = am.id
                LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                LEFT JOIN users u ON aa.user_id = u.id
                $whereClause
                GROUP BY a.id
                ORDER BY $sortColumn $order
                LIMIT $perPage OFFSET $offset
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // دریافت تعداد کل رکوردها برای صفحه‌بندی
            $countQuery = "
                SELECT COUNT(DISTINCT a.id) as total
                FROM assets a
                LEFT JOIN asset_categories ac ON a.category_id = ac.id
                LEFT JOIN asset_models am ON a.model_id = am.id
                LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                LEFT JOIN users u ON aa.user_id = u.id
                $whereClause
            ";
            
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($params);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $totalCount = isset($countResult['total']) ? $countResult['total'] : 0;
            $totalPages = ceil($totalCount / $perPage);
            
            return [
                'assets' => $assets,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'totalCount' => $totalCount,
                'perPage' => $perPage
            ];
        } catch (Exception $e) {
            error_log("Error in getAllAssets: " . $e->getMessage());
            return [
                'assets' => [],
                'totalPages' => 0,
                'currentPage' => $page,
                'totalCount' => 0,
                'perPage' => $perPage
            ];
        }
    }

    /**
     * دریافت همه تجهیزات به صورت ساده
     */
    public function getAllAssetsSimple() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM assets");
            $stmt->execute();
            // تبدیل نتیجه به آرایه
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllAssetsSimple: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لیست مکان‌های منحصر به فرد
     * 
     * @return array لیست مکان‌ها
     */
    public function getDistinctLocations() {
        try {
            // بررسی وجود ستون location
            $columns = $this->getTableColumns('assets');
            if (!in_array('location', $columns)) {
                return [];
            }
            
            $query = "SELECT DISTINCT location FROM assets WHERE location IS NOT NULL AND location != '' ORDER BY location";
            
            $connection = $this->db;
            $locations = [];
            
            if ($connection instanceof PDO) {
                $stmt = $connection->query($query);
                $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            return $locations;
        } catch (Exception $e) {
            error_log("Error in getDistinctLocations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لیست ستون‌های یک جدول
     * 
     * @param string $table نام جدول
     * @return array لیست ستون‌ها
     */
    private function getTableColumns($table) {
        try {
            $connection = $this->db;
            $columns = [];
            
            if ($connection instanceof PDO) {
                $query = "SHOW COLUMNS FROM $table";
                $stmt = $connection->query($query);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $columns[] = $row['Field'];
                }
            }
            
            return $columns;
        } catch (Exception $e) {
            error_log("Error in getTableColumns: " . $e->getMessage());
            return [];
        }
    }

    public function create($data) {
        try {
            error_log("Attempting to create asset with data: " . json_encode($data));
            
            // کد ایجاد تجهیز
            
            return $id;
        } catch (PDOException $e) {
            error_log("Detailed error in create asset: " . $e->getMessage());
            return false;
        }
    }

    /**
 * اصلاح جامع وضعیت‌های خالی یا نامعتبر در دارایی‌ها
 * این متد تمام موارد زیر را اصلاح می‌کند:
 * - وضعیت‌های NULL
 * - رشته‌های خالی ('')
 * - فاصله‌های خالی ('   ')
 * - وضعیت‌های نامعتبر (غیر از لیست وضعیت‌های مجاز)
 * 
 * @return array نتیجه عملیات با جزئیات
 */
public function fixEmptyStatuses()
{
    $result = [
        'fixed_empty' => 0,
        'fixed_invalid' => 0,
        'fixed_whitespace' => 0,
        'errors' => []
    ];

    try {
        // شروع تراکنش برای اتمیک بودن عملیات
        $this->db->beginTransaction();

        // 1. وضعیت‌های NULL یا رشته خالی یا فاصله خالی
        $stmt = $this->db->prepare("
            UPDATE assets 
            SET status = 'available', 
                updated_at = NOW() 
            WHERE status IS NULL 
               OR status = '' 
               OR TRIM(status) = ''
        ");
        $stmt->execute();
        $result['fixed_empty'] = $stmt->rowCount();

        // 2. وضعیت‌های نامعتبر (غیر از لیست مجاز)
        $validStatuses = ['available', 'assigned', 'maintenance', 'retired', 'lost', 'broken'];
        $placeholders = implode(',', array_fill(0, count($validStatuses), '?'));
        
        $stmt = $this->db->prepare("
            UPDATE assets 
            SET status = 'available', 
                updated_at = NOW() 
            WHERE status NOT IN ($placeholders)
            AND status IS NOT NULL
            AND TRIM(status) != ''
        ");
        $stmt->execute($validStatuses);
        $result['fixed_invalid'] = $stmt->rowCount();

        // 3. وضعیت‌هایی که فقط فاصله دارند (مثل '  ')
        $stmt = $this->db->prepare("
            UPDATE assets 
            SET status = 'available', 
                updated_at = NOW() 
            WHERE status REGEXP '^[[:space:]]+$'
        ");
        $stmt->execute();
        $result['fixed_whitespace'] = $stmt->rowCount();

        // اعمال تغییرات
        $this->db->commit();

        // ثبت لاگ
        error_log("Asset status fixes applied - Empty: {$result['fixed_empty']}, Invalid: {$result['fixed_invalid']}, Whitespace: {$result['fixed_whitespace']}");

    } catch (PDOException $e) {
        $this->db->rollBack();
        $errorMsg = "Failed to fix asset statuses: " . $e->getMessage();
        $result['errors'][] = $errorMsg;
        error_log($errorMsg);
    }

    return $result;
}

    /**
     * دریافت تمام تجهیزات به همراه اطلاعات کاربران تخصیص داده شده
     * 
     * @param array $filters فیلترهای جستجو
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $sortOrder ترتیب مرتب‌سازی
     * @param int $page شماره صفحه
     * @param int $perPage تعداد آیتم در هر صفحه
     * @return array
     */
    public function getAllWithUserInfo($filters = [], $sortBy = 'id', $sortOrder = 'ASC', $page = 1, $perPage = 10) {
        try {
            // اصلاح وضعیت‌های خالی قبل از انجام هر کاری
            $this->fixEmptyStatuses();

            // ساخت کوئری پایه
            $query = "
                SELECT 
                    a.id, 
                    a.name,
                    a.asset_tag,
                    a.serial_number,
                    a.status,
                    a.location,
                    m.name as model_name,
                    c.name as category_name,
                    u.id as user_id,
                    u.username,
                    u.fullname,
                    u.employee_number
                FROM 
                    assets a
                LEFT JOIN 
                    asset_models m ON a.model_id = m.id
                LEFT JOIN 
                    asset_categories c ON m.category_id = c.id
                LEFT JOIN 
                    asset_assignments aa ON a.id = aa.asset_id AND aa.is_current = 1
                LEFT JOIN 
                    users u ON aa.user_id = u.id
            ";

            // آماده‌سازی شرایط WHERE
            $where = [];
            $params = [];

            // اعمال فیلترها
            if (!empty($filters['asset_tag'])) {
                $where[] = "a.asset_tag LIKE :asset_tag";
                $params[':asset_tag'] = '%' . $filters['asset_tag'] . '%';
            }

            if (!empty($filters['category_id'])) {
                $where[] = "a.category_id = :category_id";
                $params[':category_id'] = $filters['category_id'];
            }

            if (!empty($filters['model_id'])) {
                $where[] = "a.model_id = :model_id";
                $params[':model_id'] = $filters['model_id'];
            }

            if (!empty($filters['status'])) {
                $where[] = "a.status = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['employee_number'])) {
                $where[] = "u.employee_number LIKE :employee_number";
                $params[':employee_number'] = '%' . $filters['employee_number'] . '%';
            }

            if (!empty($filters['serial'])) {
                $where[] = "a.serial_number LIKE :serial";
                $params[':serial'] = '%' . $filters['serial'] . '%';
            }

            if (!empty($filters['location'])) {
                $where[] = "a.location LIKE :location";
                $params[':location'] = '%' . $filters['location'] . '%';
            }

            // اضافه کردن شرایط WHERE به کوئری
            if (!empty($where)) {
                $query .= " WHERE " . implode(" AND ", $where);
            }

            // اعتبارسنجی و تنظیم مرتب‌سازی
            $allowedSortColumns = ['id', 'name', 'asset_tag', 'serial_number', 'status', 'model_name', 'category_name', 'location', 'username', 'fullname', 'employee_number'];
            $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'id';
            $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';

            // تعیین پیشوند جدول برای ستون مرتب‌سازی
            $sortPrefix = 'a.';
            if (in_array($sortBy, ['model_name'])) {
                $sortPrefix = 'm.';
            } elseif (in_array($sortBy, ['category_name'])) {
                $sortPrefix = 'c.';
            } elseif (in_array($sortBy, ['username', 'fullname', 'employee_number'])) {
                $sortPrefix = 'u.';
            }

            $query .= " ORDER BY {$sortPrefix}{$sortBy} {$sortOrder}";

            // محاسبه صفحه‌بندی
            $offset = ($page - 1) * $perPage;
            $query .= " LIMIT :limit OFFSET :offset";

            // اجرای کوئری
            $stmt = $this->db->prepare($query);

            // بایند کردن پارامترها
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // دریافت تعداد کل رکوردها
            $countQuery = "SELECT COUNT(*) as total FROM (" . str_replace("LIMIT :limit OFFSET :offset", "", $query) . ") as counted";
            $countStmt = $this->db->prepare($countQuery);
            
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // ترجمه وضعیت‌ها و افزودن کلاس‌های وضعیت
            foreach ($assets as &$asset) {
                $asset['status_translated'] = $this->translateStatus($asset['status'] ?? '');
                $asset['status_class'] = $this->getStatusClass($asset['status'] ?? '');
            }

            return [
                'assets' => $assets,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ];

        } catch (PDOException $e) {
            error_log("Error in getAllWithUserInfo: " . $e->getMessage());
            return [
                'assets' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 0
            ];
        }
    }

    // تابع کمکی برای دریافت کلاس وضعیت
    private function getStatusClass($status) {
        $classes = [
            'available' => 'success',
            'assigned' => 'primary',
            'maintenance' => 'warning',
            'retired' => 'secondary',
            'lost' => 'danger',
            'broken' => 'danger',
            '' => 'info',
            null => 'info'
        ];
        
        return $classes[$status] ?? 'info';
    }


}