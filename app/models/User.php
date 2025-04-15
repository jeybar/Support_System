<?php
require_once __DIR__ . '/../core/Database.php';

class User {
    private $db;

    public function __construct() {
        try {
            // بررسی کنید که آیا Database از الگوی Singleton استفاده می‌کند
            if (method_exists('Database', 'getInstance')) {
                $this->db = Database::getInstance()->getConnection();
                
                // بررسی نوع اتصال و تنظیم کاراکترست متناسب با آن
                if ($this->db instanceof PDO) {
                    $this->db->exec("SET NAMES utf8mb4");
                } elseif ($this->db instanceof mysqli) {
                    $this->db->set_charset("utf8mb4");
                }
            } else {
                // استفاده از روش قبلی
                require_once __DIR__ . '/../../config/config.php';
                $this->db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
                if ($this->db->connect_error) {
                    throw new Exception("خطا در اتصال به پایگاه داده: " . $this->db->connect_error);
                }
                
                // تنظیم کاراکترست برای اتصال mysqli
                $this->db->set_charset("utf8mb4");
            }
        } catch (Exception $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("خطا در اتصال به پایگاه داده. لطفاً با مدیر سیستم تماس بگیرید.");
        }
    }

    /**
     * اجرای کوئری با پارامترها با توجه به نوع اتصال
     * 
     * @param string $query کوئری SQL
     * @param array $params پارامترهای کوئری
     * @param string $types نوع پارامترها برای mysqli (اختیاری)
     * @return mixed نتیجه کوئری
     */
    private function executeQuery($query, $params = [], $types = '') {
        try {
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $stmt = $this->db->prepare($query);
                $stmt->execute($params);
                return $stmt;
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $stmt = $this->db->prepare($query);
                
                if (!empty($params)) {
                    // اگر types مشخص نشده باشد، آن را بر اساس پارامترها تعیین می‌کنیم
                    if (empty($types)) {
                        $types = str_repeat("s", count($params)); // فرض می‌کنیم همه پارامترها رشته هستند
                    }
                    
                    // اضافه کردن types به ابتدای آرایه پارامترها
                    array_unshift($params, $types);
                    
                    // استفاده از call_user_func_array برای پاس دادن پارامترها به bind_param
                    call_user_func_array([$stmt, 'bind_param'], $this->refValues($params));
                }
                
                $stmt->execute();
                return $stmt;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in executeQuery: " . $e->getMessage());
            return null;
        }
    }

    /**
     * دریافت نتیجه کوئری به صورت آرایه
     * 
     * @param mixed $stmt شیء statement
     * @param bool $fetchAll آیا همه نتایج را بازگرداند
     * @return array|null نتیجه کوئری
     */
    private function getQueryResult($stmt, $fetchAll = true) {
        try {
            if ($stmt === null) {
                return null;
            }
            
            if ($this->db instanceof PDO) {
                return $fetchAll ? $stmt->fetchAll(PDO::FETCH_ASSOC) : $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($this->db instanceof mysqli) {
                $result = $stmt->get_result();
                return $fetchAll ? $result->fetch_all(MYSQLI_ASSOC) : $result->fetch_assoc();
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error in getQueryResult: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تبدیل آرایه به آرایه‌ای از ارجاعات (references)
     * مورد نیاز برای bind_param در mysqli
     * 
     * @param array $arr آرایه ورودی
     * @return array آرایه خروجی با ارجاعات
     */
    private function refValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    /**
     * دریافت تمامی کاربران با امکان صفحه‌بندی و مرتب‌سازی
     * 
     * @param int $limit تعداد رکوردها در هر صفحه
     * @param int $offset شروع از رکورد
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی (asc یا desc)
     * @param string $userType نوع کاربر (اختیاری)
     * @return array لیست کاربران
     */
    public function getAllUsers($limit, $offset, $sortBy = 'username', $order = 'asc', $userType = '') {
        try {
            // اعتبارسنجی ستون مرتب‌سازی
            $validSortColumns = ['id', 'username', 'email', 'fullname', 'role_name', 'user_type', 'is_active', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'username'; // مقدار پیش‌فرض
            }
        
            // اعتبارسنجی ترتیب مرتب‌سازی
            $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        
            if (!empty($userType)) {
                // اگر نوع کاربر مشخص شده باشد، فیلتر اعمال می‌شود
                $stmt = $this->db->prepare("
                    SELECT u.id, u.username, u.email, u.fullname, u.is_active, u.user_type, 
                           u.created_at, u.updated_at, u.last_login, u.plant, u.unit,
                           r.role_name, r.id as role_id
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.user_type = ?
                    ORDER BY $sortBy $order
                    LIMIT ? OFFSET ?
                ");
                $stmt->bind_param("sii", $userType, $limit, $offset);
            } else {
                // در غیر این صورت، همه کاربران بازگردانده می‌شوند
                $stmt = $this->db->prepare("
                    SELECT u.id, u.username, u.email, u.fullname, u.is_active, u.user_type, 
                           u.created_at, u.updated_at, u.last_login, u.plant, u.unit,
                           r.role_name, r.id as role_id
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    ORDER BY $sortBy $order
                    LIMIT ? OFFSET ?
                ");
                $stmt->bind_param("ii", $limit, $offset);
            }
        
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getAllUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جستجوی کاربران
     * 
     * @param string $keyword کلمه کلیدی جستجو
     * @param string $userType نوع کاربر (اختیاری)
     * @return array نتایج جستجو
     */
    public function searchUsers($keyword, $userType = '') {
        try {
            $keyword = "%$keyword%";
        
            if (!empty($userType)) {
                // جستجو با فیلتر نوع کاربر
                $stmt = $this->db->prepare("
                    SELECT u.id, u.username, u.fullname, u.email, r.role_name, u.user_type, u.is_active,
                           u.created_at, u.updated_at, u.last_login, u.plant, u.unit
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE (u.username LIKE ? OR u.email LIKE ? OR u.fullname LIKE ? OR u.plant LIKE ? OR u.unit LIKE ?) 
                          AND u.user_type = ?
                ");
                $stmt->bind_param("ssssss", $keyword, $keyword, $keyword, $keyword, $keyword, $userType);
            } else {
                // جستجو بدون فیلتر نوع کاربر
                $stmt = $this->db->prepare("
                    SELECT u.id, u.username, u.fullname, u.email, r.role_name, u.user_type, u.is_active,
                           u.created_at, u.updated_at, u.last_login, u.plant, u.unit
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.username LIKE ? OR u.email LIKE ? OR u.fullname LIKE ? OR u.plant LIKE ? OR u.unit LIKE ?
                ");
                $stmt->bind_param("sssss", $keyword, $keyword, $keyword, $keyword, $keyword);
            }
        
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in searchUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت تعداد کل کاربران
     * 
     * @param string $userType نوع کاربر (اختیاری)
     * @return int تعداد کاربران
     */
    public function getUserCount($userType = '') {
        try {
            if (!empty($userType)) {
                $stmt = $this->db->prepare("SELECT COUNT(*) AS count FROM users WHERE user_type = ?");
                $stmt->bind_param("s", $userType);
            } else {
                $stmt = $this->db->prepare("SELECT COUNT(*) AS count FROM users");
            }
        
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            return $result['count'];
        } catch (Exception $e) {
            error_log("Error in getUserCount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * تغییر وضعیت کاربر (فعال/غیرفعال)
     * 
     * @param int $id شناسه کاربر
     * @param int $isActive وضعیت فعال بودن (0 یا 1)
     * @return bool نتیجه عملیات
     */
    public function toggleUserStatus($id, $isActive) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $isActive, $id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in toggleUserStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی نقش کاربر
     * 
     * @param int $userId شناسه کاربر
     * @param int $roleId شناسه نقش
     * @return bool نتیجه عملیات
     */
    public function updateRole($userId, $roleId) {
        try {
            // بررسی وجود کاربر
            $user = $this->getUserById($userId);
            if (!$user) {
                return false; // اگر کاربر وجود نداشته باشد
            }

            // بررسی نوع کاربر
            if ($user['user_type'] === 'کاربر شبکه') {
                return false; // نقش کاربران شبکه قابل تغییر نیست
            }

            // بررسی وجود نقش
            $roleStmt = $this->db->prepare("SELECT id FROM roles WHERE id = ?");
            $roleStmt->bind_param("i", $roleId);
            $roleStmt->execute();
            if ($roleStmt->get_result()->num_rows === 0) {
                return false; // نقش وجود ندارد
            }

            $stmt = $this->db->prepare("UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $roleId, $userId);
            
            // اجرای کوئری به‌روزرسانی
            $result = $stmt->execute();
            
            if ($result) {
                // به‌روزرسانی جدول user_roles
                $this->updateUserRoles($userId, $roleId);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error in updateRole: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی جدول user_roles
     * 
     * @param int $userId شناسه کاربر
     * @param int $roleId شناسه نقش
     * @return bool نتیجه عملیات
     */
    private function updateUserRoles($userId, $roleId) {
        try {
            // حذف نقش‌های قبلی
            $deleteStmt = $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $deleteStmt->bind_param("i", $userId);
            $deleteStmt->execute();
            
            // افزودن نقش جدید
            $insertStmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $insertStmt->bind_param("ii", $userId, $roleId);
            return $insertStmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateUserRoles: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ایجاد کاربر جدید
     * 
     * @param string $username نام کاربری
     * @param string $password رمز عبور
     * @param string $email ایمیل
     * @param string $fullname نام کامل
     * @param int $roleId شناسه نقش
     * @param string|null $phone شماره تلفن (اختیاری)
     * @param string|null $mobile شماره موبایل (اختیاری)
     * @param string|null $plant کارخانه (اختیاری)
     * @param string|null $unit واحد (اختیاری)
     * @param string $userType نوع کاربر (پیش‌فرض: local)
     * @param int $forcePasswordChange اجبار تغییر رمز عبور (پیش‌فرض: 0)
     * @return int|bool شناسه کاربر جدید یا false در صورت خطا
     */
    public function createUser($username, $password, $email, $fullname, $roleId, $phone = null, $mobile = null, $plant = null, $unit = null, $userType = 'local', $forcePasswordChange = 0) {
        try {
            // اعتبارسنجی ایمیل
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false; // ایمیل نامعتبر است
            }

            // اگر ایمیل خالی است، مقدار NULL را تنظیم کنید
            $email = !empty($email) ? $email : null;
        
            // بررسی وجود کاربر با نام کاربری یا ایمیل مشابه
            if ($this->userExists($username, $email)) {
                return false; // کاربر قبلاً ثبت شده است
            }
        
            // رمزنگاری رمز عبور
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
            // شروع تراکنش
            $this->db->begin_transaction();
            
            // درج کاربر جدید
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, email, fullname, role_id, phone, mobile, plant, unit, is_active, user_type, force_password_change, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW(), NOW())
            ");

            $email = !empty($email) ? $email : null;
            $phone = !empty($phone) ? $phone : null;
            $mobile = !empty($mobile) ? $mobile : null;
            $plant = !empty($plant) ? $plant : null;
            $unit = !empty($unit) ? $unit : null;
            $roleId = (int) $roleId; // تبدیل به عدد صحیح

            $stmt->bind_param(
                "ssssissssii", // 11 نوع داده
                $username, 
                $hashedPassword, 
                $email, 
                $fullname, 
                $roleId, 
                $phone, 
                $mobile, 
                $plant, 
                $unit, 
                $userType, 
                $forcePasswordChange
            );
        
            // اجرای کوئری
            if (!$stmt->execute()) {
                $this->db->rollback();
                error_log("Database Error in createUser: " . $stmt->error);
                return false;
            }
            
            $userId = $this->db->insert_id;
            
            // افزودن نقش کاربر به جدول user_roles
            $roleStmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $roleStmt->bind_param("ii", $userId, $roleId);
            
            if (!$roleStmt->execute()) {
                $this->db->rollback();
                error_log("Database Error in createUser (user_roles): " . $roleStmt->error);
                return false;
            }
            
            // تایید تراکنش
            $this->db->commit();
            
            return $userId;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in createUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت اطلاعات کاربر بر اساس ID
     * 
     * @param int $userId شناسه کاربر
     * @return array|null اطلاعات کاربر یا null در صورت عدم وجود
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.role_name 
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error in getUserById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جستجوی کاربر بر اساس نام کاربری
     * 
     * @param string $username نام کاربری
     * @return array|null اطلاعات کاربر یا null در صورت عدم وجود
     */
    public function findByUsername($username) {
        try {
            $query = "
                SELECT u.*, r.role_name 
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.username = ? LIMIT 1
            ";
            
            $stmt = $this->executeQuery($query, [$username]);
            return $this->getQueryResult($stmt, false);
        } catch (Exception $e) {
            error_log("Error in findByUsername: " . $e->getMessage());
            return null;
        }
    }

    /**
     * بررسی وجود کاربر با نام کاربری یا ایمیل مشخص
     * 
     * @param string $username نام کاربری
     * @param string|null $email ایمیل (اختیاری)
     * @return bool آیا کاربر وجود دارد
     */
    public function userExists($username, $email = null) {
        try {
            if ($email === null) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
            } else {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR (email = ? AND email IS NOT NULL)");
                $stmt->bind_param("ss", $username, $email);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Error in userExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی رمز عبور کاربر
     * 
     * @param int $userId شناسه کاربر
     * @param string $hashedPassword رمز عبور هش شده
     * @param bool $resetForceChange آیا اجبار تغییر رمز عبور ریست شود
     * @return bool نتیجه عملیات
     */
    public function updatePassword($userId, $hashedPassword, $resetForceChange = true) {
        try {
            if ($resetForceChange) {
                $stmt = $this->db->prepare("UPDATE users SET password = ?, force_password_change = 0, updated_at = NOW() WHERE id = ?");
            } else {
                $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            }
            $stmt->bind_param("si", $hashedPassword, $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updatePassword: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تغییر رمز عبور توسط کاربر
     * 
     * @param int $userId شناسه کاربر
     * @param string $currentPassword رمز عبور فعلی
     * @param string $newPassword رمز عبور جدید
     * @return string|bool پیام خطا یا true در صورت موفقیت
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->getUserById($userId);
            if (!$user) {
                return "کاربر یافت نشد.";
            }

            // بررسی رمز عبور فعلی
            if (!password_verify($currentPassword, $user['password'])) {
                return "رمز عبور فعلی اشتباه است.";
            }

            // بررسی پیچیدگی رمز عبور جدید
            if (!$this->isPasswordStrong($newPassword)) {
                return "رمز عبور جدید به اندازه کافی قوی نیست. رمز عبور باید حداقل 8 کاراکتر و شامل حروف بزرگ، حروف کوچک، اعداد و نمادها باشد.";
            }

            // رمزنگاری رمز عبور جدید
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $result = $this->updatePassword($userId, $hashedPassword);
            
            return $result ? true : "خطا در به‌روزرسانی رمز عبور.";
        } catch (Exception $e) {
            error_log("Error in changePassword: " . $e->getMessage());
            return "خطای سیستمی در تغییر رمز عبور.";
        }
    }

    /**
     * بررسی قدرت رمز عبور
     * 
     * @param string $password رمز عبور
     * @return bool آیا رمز عبور قوی است
     */
    private function isPasswordStrong($password) {
        // حداقل 8 کاراکتر
        if (strlen($password) < 8) {
            return false;
        }
        
        // بررسی وجود حروف بزرگ، حروف کوچک، اعداد و نمادها
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);
        
        return $uppercase && $lowercase && $number && $specialChars;
    }

    /**
     * حذف کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return bool نتیجه عملیات
     */
    public function deleteUser($userId) {
        try {
            // بررسی وجود کاربر
            $user = $this->getUserById($userId);
            if (!$user) {
                return false;
            }
            
            // شروع تراکنش
            $this->db->begin_transaction();
            
            // حذف نقش‌های کاربر
            $roleStmt = $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $roleStmt->bind_param("i", $userId);
            if (!$roleStmt->execute()) {
                $this->db->rollback();
                return false;
            }
            
            // حذف تجهیز‌های تخصیص داده شده به کاربر
            $assetStmt = $this->db->prepare("UPDATE asset_assignments SET is_current = 0, return_date = NOW() WHERE user_id = ? AND is_current = 1");
            $assetStmt->bind_param("i", $userId);
            $assetStmt->execute();
            
            // حذف کاربر
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            if (!$stmt->execute()) {
                $this->db->rollback();
                return false;
            }
            
            // تایید تراکنش
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in deleteUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت پروفایل کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return array|null اطلاعات پروفایل کاربر یا null در صورت عدم وجود
     */
    public function getUserProfile($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.email, u.phone, u.mobile, u.plant, u.unit, u.fullname, u.username, 
                       u.created_at, u.updated_at, u.last_login, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error in getUserProfile: " . $e->getMessage());
            return null;
        }
    }

    /**
     * دریافت دسترسی‌های کاربر بر اساس نقش
     * 
     * @param int $roleId شناسه نقش
     * @return array دسترسی‌های کاربر
     */
    public function getPermissions($roleId) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id, p.permission_name, p.description 
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.permission_name
            ");
            $stmt->bind_param("i", $roleId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getPermissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * به‌روزرسانی اطلاعات پروفایل کاربر
     * 
     * @param int $userId شناسه کاربر
     * @param string $email ایمیل
     * @param string|null $phone شماره تلفن (اختیاری)
     * @param string|null $mobile شماره موبایل (اختیاری)
     * @param string|null $plant کارخانه (اختیاری)
     * @param string|null $unit واحد (اختیاری)
     * @return bool نتیجه عملیات
     */
    public function updateUser($userId, $email, $phone = null, $mobile = null, $plant = null, $unit = null) {
        try {
            // اعتبارسنجی ایمیل
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false; // ایمیل نامعتبر است
            }

            // بررسی تکراری نبودن ایمیل (به جز خود کاربر)
            if (!empty($email)) {
                $checkStmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkStmt->bind_param("si", $email, $userId);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    return false; // ایمیل تکراری است
                }
            }

            // آماده‌سازی کوئری برای به‌روزرسانی اطلاعات کاربر
            $stmt = $this->db->prepare("
                UPDATE users 
                SET email = ?, phone = ?, mobile = ?, plant = ?, unit = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sssssi", $email, $phone, $mobile, $plant, $unit, $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی نوع کاربر
     * 
     * @param int $userId شناسه کاربر
     * @param string $userType نوع کاربر
     * @return bool نتیجه عملیات
     */
    public function updateUserType($userId, $userType) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET user_type = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $userType, $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateUserType: " . $e->getMessage());
            return false;
        }
    }

    /**
     * بررسی تکمیل بودن پروفایل
     * 
     * @param int $userId شناسه کاربر
     * @return bool آیا پروفایل کامل است
     */
    public function isProfileComplete($userId) {
        try {
            // بازیابی اطلاعات کاربر
            $query = "
                SELECT email, phone, plant, unit 
                FROM users 
                WHERE id = :userId
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
            // بررسی تکمیل بودن فیلدهای ضروری
            if (!$result || empty($result['email']) || empty($result['phone']) || empty($result['plant']) || empty($result['unit'])) {
                return false; // پروفایل ناقص است
            }
        
            return true; // پروفایل کامل است
        } catch (Exception $e) {
            error_log("Error in isProfileComplete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * به‌روزرسانی اطلاعات اصلی کاربر
     * 
     * @param int $id شناسه کاربر
     * @param array $data داده‌های کاربر
     * @return bool نتیجه عملیات
     */
    public function updateUserDetails($id, $data) {
        try {
            // بررسی تکراری نبودن نام کاربری (به جز خود کاربر)
            $checkStmt = $this->db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $checkStmt->bind_param("si", $data['username'], $id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                return false; // نام کاربری تکراری است
            }
            
            // آماده‌سازی کوئری برای به‌روزرسانی اطلاعات کاربر
            $stmt = $this->db->prepare("
                UPDATE users 
                SET username = ?, fullname = ?, role_id = ?, user_type = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssissi", 
                $data['username'], 
                $data['fullname'], 
                $data['role_id'], 
                $data['user_type'], 
                $data['is_active'], 
                $id
            );
        
            $result = $stmt->execute();
            
            if ($result) {
                // به‌روزرسانی جدول user_roles
                $this->updateUserRoles($id, $data['role_id']);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error in updateUserDetails: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت تعداد کاربران بر اساس وضعیت
     * 
     * @return array آمار کاربران
     */
    public function getUsersByStatus() {
        try {
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "SELECT 
                            CASE 
                                WHEN is_active = 1 THEN 'active'
                                WHEN is_active = 0 THEN 'inactive'
                            END AS status, 
                            COUNT(*) AS count 
                        FROM users 
                        GROUP BY is_active";

                $stmt = $this->db->query($query);
                
                // تبدیل نتایج به آرایه
                $results = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $results[] = $row;
                }
                
                // اطمینان از وجود هر دو وضعیت در نتایج
                $hasActive = false;
                $hasInactive = false;
                
                foreach ($results as $row) {
                    if ($row['status'] === 'active') $hasActive = true;
                    if ($row['status'] === 'inactive') $hasInactive = true;
                }
                
                if (!$hasActive) $results[] = ['status' => 'active', 'count' => 0];
                if (!$hasInactive) $results[] = ['status' => 'inactive', 'count' => 0];
                
                return $results;
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli (کد اصلی)
                $query = "SELECT 
                            CASE 
                                WHEN is_active = 1 THEN 'active'
                                WHEN is_active = 0 THEN 'inactive'
                            END AS status, 
                            COUNT(*) AS count 
                        FROM users 
                        GROUP BY is_active";

                $stmt = $this->db->query($query);

                // بررسی اینکه کوئری به درستی اجرا شده باشد
                if (!$stmt) {
                    throw new Exception('خطا در اجرای کوئری: ' . $this->db->error);
                }

                // تبدیل نتایج به آرایه
                $results = [];
                while ($row = $stmt->fetch_assoc()) {
                    $results[] = $row;
                }
                
                // اطمینان از وجود هر دو وضعیت در نتایج
                $hasActive = false;
                $hasInactive = false;
                
                foreach ($results as $row) {
                    if ($row['status'] === 'active') $hasActive = true;
                    if ($row['status'] === 'inactive') $hasInactive = true;
                }
                
                if (!$hasActive) $results[] = ['status' => 'active', 'count' => 0];
                if (!$hasInactive) $results[] = ['status' => 'inactive', 'count' => 0];
                
                return $results;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است.");
            }
        } catch (Exception $e) {
            error_log("Error in getUsersByStatus: " . $e->getMessage());
            return [
                ['status' => 'active', 'count' => 0],
                ['status' => 'inactive', 'count' => 0]
            ];
        }
    }

    /**
     * دریافت لیست کاربران بر اساس نقش
     * 
     * @param string $roleName نام نقش
     * @return array لیست کاربران
     */
    public function getUsersByRole($roleName) {
        try {
            // ثبت لاگ برای اشکال‌زدایی
            error_log("getUsersByRole called with role: " . $roleName);
            
            // ابتدا شناسه نقش را بر اساس نام نقش پیدا می‌کنیم
            $roleQuery = "SELECT id FROM roles WHERE role_name = ?";
            $roleStmt = $this->db->prepare($roleQuery);
            $roleStmt->bind_param('s', $roleName);
            $roleStmt->execute();
            $roleResult = $roleStmt->get_result();
            
            if ($roleResult->num_rows === 0) {
                error_log("No role found with name: " . $roleName);
                return [];
            }
            
            $roleId = $roleResult->fetch_assoc()['id'];
            error_log("Found role ID: " . $roleId);
            
            // سپس کاربران با این نقش را پیدا می‌کنیم
            $query = "SELECT u.id, u.fullname, u.username, u.email, u.plant, u.unit
                      FROM users u
                      WHERE u.role_id = ? AND u.is_active = 1
                      ORDER BY u.fullname";
        
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Found " . count($users) . " users with role ID " . $roleId);
            
            return $users;
        } catch (Exception $e) {
            error_log("Error in getUsersByRole: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت لیست کاربران بر اساس شناسه نقش
     * 
     * @param int $roleId شناسه نقش
     * @return array لیست کاربران
     */
    public function getUsersByRoleId($roleId) {
        try {
            $query = "SELECT u.id, u.fullname, u.username, u.email, u.plant, u.unit
                    FROM users u
                    WHERE u.role_id = ? AND u.is_active = 1
                    ORDER BY u.fullname";

            $stmt = $this->db->prepare($query);
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUsersByRoleId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت کاربران با اعمال فیلترها
     * 
     * @param array $filters فیلترها
     * @param int $limit تعداد رکوردها در هر صفحه
     * @param int $offset شروع از رکورد
     * @param string $sortBy ستون مرتب‌سازی
     * @param string $order ترتیب مرتب‌سازی
     * @return array لیست کاربران
     */
    public function getFilteredUsers($filters, $limit, $offset, $sortBy = 'username', $order = 'asc') {
        try {
            error_log("getFilteredUsers called with filters: " . json_encode($filters));
            
            // اعتبارسنجی ستون مرتب‌سازی
            $validSortColumns = ['id', 'username', 'email', 'fullname', 'role_name', 'user_type', 'is_active', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'username'; // مقدار پیش‌فرض
            }

            // اعتبارسنجی ترتیب مرتب‌سازی
            $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

            // ساخت شرط WHERE
            $whereConditions = [];
            $params = [];

            if (!empty($filters['username'])) {
                $whereConditions[] = "u.username LIKE ?";
                $params[] = '%' . $filters['username'] . '%';
            }

            if (!empty($filters['fullname'])) {
                $whereConditions[] = "u.fullname LIKE ?";
                $params[] = '%' . $filters['fullname'] . '%';
            }

            if (!empty($filters['role'])) {
                $whereConditions[] = "u.role_id = ?";
                $params[] = $filters['role'];
            }

            if (!empty($filters['user_type'])) {
                $whereConditions[] = "u.user_type = ?";
                $params[] = $filters['user_type'];
            }

            if (isset($filters['status']) && $filters['status'] !== '') {
                $whereConditions[] = "u.is_active = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['created_date'])) {
                $whereConditions[] = "DATE(u.created_at) = ?";
                $params[] = $filters['created_date'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditions[] = "u.plant LIKE ?";
                $params[] = '%' . $filters['plant'] . '%';
            }
            
            if (!empty($filters['unit'])) {
                $whereConditions[] = "u.unit LIKE ?";
                $params[] = '%' . $filters['unit'] . '%';
            }

            // ساخت بخش WHERE کوئری
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            }

            // تبدیل به عدد صحیح برای اطمینان
            $limit = (int)$limit;
            $offset = (int)$offset;

            // ساخت کوئری کامل با مقادیر LIMIT و OFFSET به صورت مستقیم
            $query = "
                SELECT u.id, u.username, u.email, u.fullname, u.is_active, u.user_type, 
                    u.created_at, u.updated_at, u.last_login, u.plant, u.unit,
                    r.role_name, r.id as role_id
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                $whereClause
                ORDER BY $sortBy $order
                LIMIT $limit OFFSET $offset
            ";

            error_log("Final query: " . $query);
            error_log("Params: " . json_encode($params));

            // اجرای کوئری با توجه به نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->execute($params);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("PDO query returned " . count($result) . " rows");
                return $result;
            } elseif ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                
                if (!empty($params)) {
                    // تعیین نوع پارامترها
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } elseif (is_string($param)) {
                            $types .= 's';
                        } else {
                            $types .= 's'; // پیش‌فرض رشته
                        }
                    }
                    
                    // استفاده از bind_param
                    if (!empty($types)) {
                        $bindParams = array_merge([$types], $params);
                        call_user_func_array([$stmt, 'bind_param'], $this->refValues($bindParams));
                    }
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_all(MYSQLI_ASSOC);
                error_log("mysqli query returned " . count($data) . " rows");
                return $data;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است: " . gettype($this->db));
            }
        } catch (Exception $e) {
            error_log("Error in getFilteredUsers: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * دریافت تعداد کل کاربران با اعمال فیلترها
     * 
     * @param array $filters فیلترها
     * @return int تعداد کاربران
     */
    public function getFilteredUserCount($filters) {
        try {
            // ساخت شرط WHERE
            $whereConditions = [];
            $params = [];

            if (!empty($filters['username'])) {
                $whereConditions[] = "u.username LIKE ?";
                $params[] = '%' . $filters['username'] . '%';
            }

            if (!empty($filters['fullname'])) {
                $whereConditions[] = "u.fullname LIKE ?";
                $params[] = '%' . $filters['fullname'] . '%';
            }

            if (!empty($filters['role'])) {
                $whereConditions[] = "u.role_id = ?";
                $params[] = $filters['role'];
            }

            if (!empty($filters['user_type'])) {
                $whereConditions[] = "u.user_type = ?";
                $params[] = $filters['user_type'];
            }

            if (isset($filters['status']) && $filters['status'] !== '') {
                $whereConditions[] = "u.is_active = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['created_date'])) {
                $whereConditions[] = "DATE(u.created_at) = ?";
                $params[] = $filters['created_date'];
            }
            
            if (!empty($filters['plant'])) {
                $whereConditions[] = "u.plant LIKE ?";
                $params[] = '%' . $filters['plant'] . '%';
            }
            
            if (!empty($filters['unit'])) {
                $whereConditions[] = "u.unit LIKE ?";
                $params[] = '%' . $filters['unit'] . '%';
            }

            // ساخت بخش WHERE کوئری
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            }

            // ساخت و اجرای کوئری
            $query = "
                SELECT COUNT(*) as count
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                $whereClause
            ";

            // اجرای کوئری با توجه به نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? (int)$result['count'] : 0;
            } elseif ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                
                if (!empty($params)) {
                    // تعیین نوع پارامترها
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } elseif (is_string($param)) {
                            $types .= 's';
                        } else {
                            $types .= 's'; // پیش‌فرض رشته
                        }
                    }
                    
                    // استفاده از bind_param
                    if (!empty($types)) {
                        $bindParams = array_merge([$types], $params);
                        call_user_func_array([$stmt, 'bind_param'], $this->refValues($bindParams));
                    }
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_assoc();
                return $data ? (int)$data['count'] : 0;
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است: " . gettype($this->db));
            }
        } catch (Exception $e) {
            error_log("Error in getFilteredUserCount: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * بررسی می‌کند که آیا کاربر دسترسی مشخصی را دارد یا خیر
     * 
     * @param int $userId شناسه کاربر
     * @param string $permissionName نام دسترسی
     * @return bool آیا کاربر دسترسی دارد
     */
    public function hasPermission($userId, $permissionName) {
        try {
            $query = "SELECT COUNT(*) as count FROM users u
                    JOIN roles r ON u.role_id = r.id
                    JOIN role_permissions rp ON r.id = rp.role_id
                    JOIN permissions p ON rp.permission_id = p.id
                    WHERE u.id = ? AND p.permission_name = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("is", $userId, $permissionName);
            $stmt->execute();
            
            $result = $stmt->get_result()->fetch_assoc();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Error in hasPermission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت تمام دسترسی‌های کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return array لیست دسترسی‌ها
     */
    public function getUserPermissions($userId) {
        try {
            $query = "SELECT DISTINCT p.permission_name, p.description FROM users u
                    JOIN roles r ON u.role_id = r.id
                    JOIN role_permissions rp ON r.id = rp.role_id
                    JOIN permissions p ON rp.permission_id = p.id
                    WHERE u.id = ?
                    ORDER BY p.permission_name";
            
            $stmt = $this->executeQuery($query, [$userId]);
            $result = $this->getQueryResult($stmt, true);
            
            $permissions = [];
            if ($result) {
                foreach ($result as $row) {
                    $permissions[] = $row['permission_name'];
                }
            }
            
            return $permissions;
        } catch (Exception $e) {
            error_log("Error in getUserPermissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ثبت آخرین ورود کاربر
     * 
     * @param int $userId شناسه کاربر
     * @return bool نتیجه عملیات
     */
    public function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateLastLogin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت آمار کاربران بر اساس نقش
     * 
     * @return array آمار کاربران
     */
    public function getUserStatsByRole() {
        try {
            $query = "SELECT r.role_name, COUNT(u.id) as count
                      FROM roles r
                      LEFT JOIN users u ON r.id = u.role_id
                      GROUP BY r.id, r.role_name
                      ORDER BY count DESC";
            
            $result = $this->db->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUserStatsByRole: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار کاربران بر اساس نوع کاربر
     * 
     * @return array آمار کاربران
     */
    public function getUserStatsByType() {
        try {
            $query = "SELECT user_type, COUNT(*) as count
                      FROM users
                      GROUP BY user_type
                      ORDER BY count DESC";
            
            $result = $this->db->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUserStatsByType: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار کاربران بر اساس واحد
     * 
     * @return array آمار کاربران
     */
    public function getUserStatsByUnit() {
        try {
            $query = "SELECT unit, COUNT(*) as count
                      FROM users
                      WHERE unit IS NOT NULL AND unit != ''
                      GROUP BY unit
                      ORDER BY count DESC";
            
            $result = $this->db->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUserStatsByUnit: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار کاربران بر اساس کارخانه
     * 
     * @return array آمار کاربران
     */
    public function getUserStatsByPlant() {
        try {
            $query = "SELECT plant, COUNT(*) as count
                      FROM users
                      WHERE plant IS NOT NULL AND plant != ''
                      GROUP BY plant
                      ORDER BY count DESC";
            
            $result = $this->db->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUserStatsByPlant: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار ثبت‌نام کاربران در طول زمان
     * 
     * @param int $months تعداد ماه‌های اخیر
     * @return array روند ثبت‌نام کاربران
     */
    public function getUserRegistrationTrend($months = 12) {
        try {
            $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                      COUNT(*) as count
                      FROM users
                      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                      ORDER BY month";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $months);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUserRegistrationTrend: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت کاربران با آخرین ورود در بازه زمانی مشخص
     * 
     * @param int $days تعداد روزهای اخیر
     * @return array لیست کاربران
     */
    public function getRecentlyActiveUsers($days = 7) {
        try {
            $query = "SELECT u.id, u.username, u.fullname, u.email, u.last_login, r.role_name
                      FROM users u
                      LEFT JOIN roles r ON u.role_id = r.id
                      WHERE u.last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)
                      ORDER BY u.last_login DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getRecentlyActiveUsers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت کاربران غیرفعال در بازه زمانی مشخص
     * 
     * @param int $days تعداد روزهای اخیر
     * @return array لیست کاربران
     */
    public function getInactiveUsers($days = 30) {
        try {
            $query = "SELECT u.id, u.username, u.fullname, u.email, u.last_login, r.role_name
                      FROM users u
                      LEFT JOIN roles r ON u.role_id = r.id
                      WHERE u.is_active = 1 AND (u.last_login IS NULL OR u.last_login < DATE_SUB(NOW(), INTERVAL ? DAY))
                      ORDER BY u.last_login ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getInactiveUsers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت کاربران با بیشترین تجهیز‌های تخصیص داده شده
     * 
     * @param int $limit تعداد نتایج
     * @return array لیست کاربران
     */
    public function getUsersWithMostAssets($limit = 10) {
        try {
            $query = "SELECT u.id, u.username, u.fullname, COUNT(aa.id) as asset_count
                      FROM users u
                      JOIN asset_assignments aa ON u.id = aa.user_id
                      WHERE aa.is_current = 1
                      GROUP BY u.id, u.username, u.fullname
                      ORDER BY asset_count DESC
                      LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUsersWithMostAssets: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت لیست واحدها
     * 
     * @return array لیست واحدها
     */
    public function getUniqueUnits() {
        try {
            $query = "SELECT DISTINCT unit FROM users WHERE unit IS NOT NULL AND unit != '' ORDER BY unit";
            $result = $this->db->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUniqueUnits: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت لیست کارخانه‌ها
     * 
     * @return array لیست کارخانه‌ها
     */
    public function getUniquePlants() {
        try {
            $query = "SELECT DISTINCT plant FROM users WHERE plant IS NOT NULL AND plant != '' ORDER BY plant";
            $result = $this->db->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUniquePlants: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت کاربران یک واحد
     * 
     * @param string $unit نام واحد
     * @return array لیست کاربران
     */
    public function getUsersByUnit($unit) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.username, u.fullname, u.email, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.unit = ? AND u.is_active = 1
                ORDER BY u.fullname
            ");
            $stmt->bind_param("s", $unit);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUsersByUnit: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت کاربران یک کارخانه
     * 
     * @param string $plant نام کارخانه
     * @return array لیست کاربران
     */
    public function getUsersByPlant($plant) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.username, u.fullname, u.email, r.role_name, u.unit
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.plant = ? AND u.is_active = 1
                ORDER BY u.unit, u.fullname
            ");
            $stmt->bind_param("s", $plant);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUsersByPlant: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تنظیم اجبار تغییر رمز عبور
     * 
     * @param int $userId شناسه کاربر
     * @param int $force وضعیت اجبار (0 یا 1)
     * @return bool نتیجه عملیات
     */
    public function setForcePasswordChange($userId, $force) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET force_password_change = ? WHERE id = ?");
            $stmt->bind_param("ii", $force, $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in setForcePasswordChange: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * بازنشانی رمز عبور کاربر
     * 
     * @param int $userId شناسه کاربر
     * @param string $newPassword رمز عبور جدید
     * @return bool نتیجه عملیات
     */
    public function resetPassword($userId, $newPassword) {
        try {
            // رمزنگاری رمز عبور جدید
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            // تنظیم رمز عبور جدید و اجبار تغییر آن
            $stmt = $this->db->prepare("UPDATE users SET password = ?, force_password_change = 1, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in resetPassword: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ایجاد رمز عبور تصادفی
     * 
     * @param int $length طول رمز عبور
     * @return string رمز عبور تصادفی
     */
    public function generateRandomPassword($length = 10) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * بستن اتصال پایگاه داده
     */
    public function __destruct() {
        if ($this->db instanceof mysqli) {
            $this->db->close();
        }
    }

    /**
     * دریافت تمامی کاربران بدون صفحه‌بندی برای استفاده در دراپ‌داون‌ها
     * 
     * @return array لیست کاربران
     */
    public function getAllUsersForDropdown() {
        try {
            // بررسی وجود ستون employee_number
            $checkQuery = "SHOW COLUMNS FROM users LIKE 'employee_number'";
            $hasEmployeeNumber = false;
            
            if ($this->db instanceof PDO) {
                $stmt = $this->db->query($checkQuery);
                $hasEmployeeNumber = $stmt->rowCount() > 0;
            } elseif ($this->db instanceof mysqli) {
                $result = $this->db->query($checkQuery);
                $hasEmployeeNumber = $result && $result->num_rows > 0;
            }
            
            // ساخت کوئری بر اساس ستون‌های موجود
            $query = "SELECT id, username, fullname, email";
            
            if ($hasEmployeeNumber) {
                $query .= ", employee_number";
            }
            
            // بررسی وجود ستون‌های plant و unit
            $checkPlantQuery = "SHOW COLUMNS FROM users LIKE 'plant'";
            $hasPlant = false;
            
            if ($this->db instanceof PDO) {
                $stmt = $this->db->query($checkPlantQuery);
                $hasPlant = $stmt->rowCount() > 0;
            } elseif ($this->db instanceof mysqli) {
                $result = $this->db->query($checkPlantQuery);
                $hasPlant = $result && $result->num_rows > 0;
            }
            
            if ($hasPlant) {
                $query .= ", plant";
            }
            
            $checkUnitQuery = "SHOW COLUMNS FROM users LIKE 'unit'";
            $hasUnit = false;
            
            if ($this->db instanceof PDO) {
                $stmt = $this->db->query($checkUnitQuery);
                $hasUnit = $stmt->rowCount() > 0;
            } elseif ($this->db instanceof mysqli) {
                $result = $this->db->query($checkUnitQuery);
                $hasUnit = $result && $result->num_rows > 0;
            }
            
            if ($hasUnit) {
                $query .= ", unit";
            }
            
            $query .= " FROM users WHERE is_active = 1 ORDER BY fullname";
            
            // اجرای کوئری
            if ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($this->db instanceof mysqli) {
                $result = $this->db->query($query);
                if ($result) {
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                } else {
                    $data = [];
                    error_log("mysqli query failed in getAllUsersForDropdown: " . $this->db->error);
                }
                $result = $data;
            } else {
                $result = [];
                error_log("Unknown database connection type in getAllUsersForDropdown");
            }
            
            error_log("getAllUsersForDropdown result count: " . count($result));
            return $result;
        } catch (Exception $e) {
            error_log("Error in getAllUsersForDropdown: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * جستجوی کاربران بر اساس یک فیلد مشخص
     * 
     * @param string $field نام فیلد برای جستجو
     * @param string $value مقدار مورد جستجو
     * @param bool $useLike استفاده از LIKE برای جستجوی انعطاف‌پذیر
     * @return array لیست کاربران یافت شده
     */
    public function searchUsersByField($field, $value, $useLike = false) {
        try {
            // اطمینان از معتبر بودن نام فیلد
            $allowedFields = ['username', 'fullname', 'email', 'phone', 'mobile'];
            if (!in_array($field, $allowedFields)) {
                error_log("Invalid search field: {$field}");
                return [];
            }
            
            // ساخت کوئری بر اساس نوع جستجو
            if ($useLike) {
                $query = "SELECT u.id, u.username, u.fullname, u.email, u.phone, u.mobile, 
                            u.plant, u.unit, u.is_active, r.role_name
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.id
                        WHERE u.{$field} LIKE ?
                        LIMIT 10";
                $params = ["%{$value}%"];
            } else {
                $query = "SELECT u.id, u.username, u.fullname, u.email, u.phone, u.mobile, 
                            u.plant, u.unit, u.is_active, r.role_name
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.id
                        WHERE u.{$field} = ?
                        LIMIT 10";
                $params = [$value];
            }
            
            $stmt = $this->executeQuery($query, $params);
            return $this->getQueryResult($stmt, true);
        } catch (Exception $e) {
            error_log("Error in searchUsersByField: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت اطلاعات کاربر با نام کاربری
     * 
     * @param string $username نام کاربری
     * @return array|false اطلاعات کاربر یا false در صورت عدم وجود
     */
    public function getUserByUsername($username) {
        try {
            // لاگ کردن درخواست
            error_log("getUserByUsername called with username: $username");
            
            // بررسی نوع اتصال به دیتابیس
            if ($this->db instanceof PDO) {
                // اجرا با PDO
                $query = "SELECT u.id, u.username, u.fullname, u.email, u.phone, u.mobile, 
                            u.plant, u.unit, u.is_active, r.role_name
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.id
                        WHERE u.username = ?";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([$username]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } elseif ($this->db instanceof mysqli) {
                // اجرا با mysqli
                $query = "SELECT u.id, u.username, u.fullname, u.email, u.phone, u.mobile, 
                            u.plant, u.unit, u.is_active, r.role_name
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.id
                        WHERE u.username = ?";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
            } else {
                throw new Exception("نوع اتصال به دیتابیس نامعتبر است");
            }
            
            // لاگ کردن نتیجه
            error_log("getUserByUsername result: " . ($result ? json_encode($result) : 'No result'));
            
            return $result;
        } catch (Exception $e) {
            error_log("Error in getUserByUsername: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            return false;
        }
    }
}