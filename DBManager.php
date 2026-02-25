<?php
// includes/DBManager.php

class DBManager {
    // 1. SETTINGS
    // Your exact Hostinger Database settings
    private $cloud_host = 'srv1254.hstgr.io'; 
    private $cloud_db   = 'u106033383_jemerald_cloud';
    private $cloud_user = 'u106033383_jemerald1234';
    private $cloud_pass = 'Wearelive_1234';
    
    // The name of your local file
    private $db_filename = 'warehouse_v2.0.db'; 

    public $is_fallback = false; 
    public $current_source = ''; 
  

    public $connection_error = ''; 

    public function getConnection($requested_branch, $local_branch_code = null) {
        
        // Dynamic Context Resolution
        if ($local_branch_code === null) {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $local_branch_code = $_SESSION['branch_code']; 
        }
        
        // A. If requesting Local Branch, go straight to Local
        if ($requested_branch === $local_branch_code || empty($requested_branch)) {
            $this->current_source = 'local';
            return $this->getLocalConnection();
        }

        // B. Try Cloud Connection
        try {
            $dsn = "mysql:host={$this->cloud_host};dbname={$this->cloud_db};charset=utf8mb4";
            
            // [CRITICAL FIX] Correct Persistence for PDO
            // This reuses connections to satisfy Hostinger's optimization advice
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 15, // Wait up to 15s before failing
                PDO::ATTR_PERSISTENT => true // <--- The "Reuse Connection" Fix
            ];

            $pdo = new PDO($dsn, $this->cloud_user, $this->cloud_pass, $options);
            
            $this->current_source = 'cloud';
            return $pdo;
            
        } catch (PDOException $e) {
            // C. Fallback to Local
            $this->is_fallback = true;
            $this->current_source = 'local';
            $this->connection_error = $e->getMessage(); 
            return $this->getLocalConnection();
        }
    }
    
    private function getLocalConnection() {
        // Robust Path Finding
        $candidates = [
            dirname(__DIR__, 2) . '/database/' . $this->db_filename, 
            dirname(__DIR__) . '/database/' . $this->db_filename,    
            __DIR__ . '/' . $this->db_filename                       
        ];

        $db_path = null;
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $db_path = $path;
                break;
            }
        }

        if (!$db_path) {
            $db_path = $this->db_filename; 
        }
        
        try {
            $pdo = new PDO("sqlite:$db_path");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Critical DB Failure: ' . $e->getMessage()]));
        }
    }
}
?>