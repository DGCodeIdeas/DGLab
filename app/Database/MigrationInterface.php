<?php

namespace DGLab\Database;

/**
 * Migration Interface
 */
interface MigrationInterface
{
    /**
     * Run the migration
     */
    public function up(): void;

    /**
     * Reverse the migration
     */
    public function down(): void;
}
