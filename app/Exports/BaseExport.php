<?php

namespace App\Exports;

use Illuminate\Support\Collection;

abstract class BaseExport
{
    /**
     * 查询数据
     */
    abstract public function query(array $filters = []): Collection;

    /**
     * 默认选中列 (key => label)
     */
    abstract public function defaultColumns(): array;

    /**
     * 所有可用列 (key => label)
     */
    abstract public function availableColumns(): array;
}
