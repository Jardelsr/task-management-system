<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Command as MongoCommand;
use Exception;

class TestDatabaseConnections extends Command
{
    protected $signature = 'db:test-connections {--mysql} {--mongo} {--details}';
    protected $description = 'Test database connections (MySQL and MongoDB)';

    public function handle()
    {
        $testMySQL = $this->option('mysql') || (!$this->option('mysql') && !$this->option('mongo'));
        $testMongo = $this->option('mongo') || (!$this->option('mysql') && !$this->option('mongo'));
        $details = $this->option('details');

        $this->info('🔍 Testing Database Connections...');
        $this->newLine();

        $allPassed = true;

        // Test MySQL Connection
        if ($testMySQL) {
            $allPassed = $this->testMySQLConnection($details) && $allPassed;
        }

        // Test MongoDB Connection  
        if ($testMongo) {
            $allPassed = $this->testMongoDBConnection($details) && $allPassed;
        }

        $this->newLine();
        
        if ($allPassed) {
            $this->info('✅ All database connections are working correctly!');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Some database connections failed!');
            return Command::FAILURE;
        }
    }

    private function testMySQLConnection($details = false): bool
    {
        $this->info('Testing MySQL Connection...');
        
        try {
            // Test basic connection
            $connection = DB::connection('mysql');
            $pdo = $connection->getPdo();
            
            if ($details) {
                $this->line('  ✓ PDO Connection established');
            }
            
            // Test database query
            $database = $connection->select('SELECT DATABASE() as db')[0]->db;
            $version = $connection->select('SELECT VERSION() as version')[0]->version;
            
            if ($details) {
                $this->line("  ✓ Database: {$database}");
                $this->line("  ✓ MySQL Version: {$version}");
            }
            
            // Test table access
            $tables = $connection->select('SHOW TABLES');
            $tableCount = count($tables);
            
            if ($details) {
                $this->line("  ✓ Accessible tables: {$tableCount}");
                if ($tableCount > 0) {
                    foreach ($tables as $table) {
                        $tableName = array_values((array)$table)[0];
                        $this->line("    - {$tableName}");
                    }
                }
            }
            
            $this->info('✅ MySQL: SUCCESS');
            return true;
            
        } catch (Exception $e) {
            $this->error('❌ MySQL: FAILED - ' . $e->getMessage());
            if ($details) {
                $this->line('  Error details: ' . $e->getTraceAsString());
            }
            return false;
        }
    }

    private function testMongoDBConnection($details = false): bool
    {
        $this->info('Testing MongoDB Connection...');
        
        try {
            // Check if MongoDB extension is loaded
            if (!extension_loaded('mongodb')) {
                throw new Exception('MongoDB extension not loaded');
            }

            if ($details) {
                $this->line('  ✓ MongoDB extension loaded');
            }
            
            // Create connection using environment variables
            $host = env('MONGO_HOST', 'localhost');
            $port = env('MONGO_PORT', 27017);
            $database = env('MONGO_DATABASE', 'task_logs');
            
            $connectionString = "mongodb://{$host}:{$port}";
            $manager = new Manager($connectionString);
            
            if ($details) {
                $this->line("  ✓ Connection string: {$connectionString}");
            }
            
            // Test connection with ping
            $command = new MongoCommand(['ping' => 1]);
            $result = $manager->executeCommand('admin', $command);
            
            if ($details) {
                $this->line('  ✓ Ping successful');
            }
            
            // List databases
            $listDbsCommand = new MongoCommand(['listDatabases' => 1]);
            $dbsResult = $manager->executeCommand('admin', $listDbsCommand);
            $databases = $dbsResult->toArray()[0]->databases ?? [];
            
            if ($details) {
                $this->line('  ✓ Available databases:');
                foreach ($databases as $db) {
                    $this->line("    - {$db->name}");
                }
            }
            
            // Test target database access
            if ($database) {
                $listCollsCommand = new MongoCommand(['listCollections' => 1]);
                $collsResult = $manager->executeCommand($database, $listCollsCommand);
                $collections = $collsResult->toArray();
                
                if ($details) {
                    $this->line("  ✓ Collections in '{$database}': " . count($collections));
                    foreach ($collections as $coll) {
                        $this->line("    - {$coll->name}");
                    }
                }
            }
            
            $this->info('✅ MongoDB: SUCCESS');
            return true;
            
        } catch (Exception $e) {
            $this->error('❌ MongoDB: FAILED - ' . $e->getMessage());
            if ($details) {
                $this->line('  Error details: ' . $e->getTraceAsString());
            }
            return false;
        }
    }
}