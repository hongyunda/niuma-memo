<?php
use think\migration\Migrator;

class NoteAutoTitle extends Migrator
{
    public function up()
    {
        // Open-source edition: allow schema.sql imports to register migration history safely.
        $table = $this->table('note');
        $columns = [
            'title_status' => ['string', ['limit' => 16, 'default' => 'idle']],
            'title_input_hash' => ['string', ['limit' => 64, 'default' => '']],
            'title_started_at' => ['datetime', ['null' => true]],
            'title_error' => ['string', ['limit' => 255, 'default' => '']],
        ];
        foreach ($columns as $name => [$type, $options]) {
            if (!$table->hasColumn($name)) {
                $table->addColumn($name, $type, $options)->update();
            }
        }
        if (!$table->hasIndex(['title_status'])) {
            $table->addIndex(['title_status'])->update();
        }
    }

    public function down()
    {
        $this->table('note')->removeIndex(['title_status'])
            ->removeColumn('title_status')->removeColumn('title_input_hash')
            ->removeColumn('title_started_at')->removeColumn('title_error')->update();
    }
}
