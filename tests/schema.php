<?php

use Illuminate\Support\Facades\DB;

/** A stable dump of every migrated table's columns, indexes and foreign keys, for comparing schemas across releases. */
return function (): array {
    $schema = [];
    $tables = DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' and name != 'migrations' order by name");
    foreach ($tables as $table) {
        $name = $table->name;
        $columns = array_map(fn ($c) => [$c->name, strtolower($c->type), (int) $c->notnull, $c->dflt_value, (int) $c->pk], DB::select('pragma table_info("'.$name.'")'));
        $indexes = [];
        foreach (DB::select('pragma index_list("'.$name.'")') as $index) {
            $indexes[] = [(int) $index->unique, $index->origin, array_map(fn ($c) => $c->name, DB::select('pragma index_info("'.$index->name.'")'))];
        }
        sort($indexes);
        $foreign = array_map(fn ($f) => [$f->from, $f->table, $f->to, $f->on_update, $f->on_delete], DB::select('pragma foreign_key_list("'.$name.'")'));
        sort($foreign);
        $schema[$name] = ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreign];
    }

    return $schema;
};
