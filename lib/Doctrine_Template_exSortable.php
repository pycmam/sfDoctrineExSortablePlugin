<?php

/**
 * Behaviour: exSortable
 *
 * Options:
 *   - mode       - append|prepend TODO: replace with sort order (asc|desc)
 *   - key_column - ключевая колонка для сортировки, id по-умолчанию
 */
class Doctrine_Template_exSortable extends Doctrine_Template
{
    const MODE_APPEND  = 'append';
    const MODE_PREPEND = 'prepend';


    /**
     * Добавить колонку `sort`
     */
    public function setTableDefinition()
    {
        $listener = new Doctrine_Template_exSortable_Listener;

        $column = array(
            'type'     => 'integer',
            'length'   => 1,
            'default'  => 0,
            'notnull'  => true,
            'unsigned' => true,
        );
        if (isset($this->_options['mode']) && self::MODE_PREPEND == $this->_options['mode']) {
            $column['default'] = 255;
            $listener->setOption('mode', self::MODE_PREPEND);
        } else {
            $listener->setOption('mode', self::MODE_APPEND);
        }

        if (!isset($this->_options['key_column'])) {
            $this->_options['key_column'] = 'id';
        }
        $listener->setOption('key_column', $this->_options['key_column']);

        $this->hasColumn('sort', $column['type'], $column['length'], $column);
        $this->addListener($listener, __CLASS__); // Add named unique listener
    }


    /**
     * TableProxy: Установить новый порядок сортировки
     *
     * Необходимо передавать массив всех элементов.
     * Если передать только часть, то полученные индексы сортировки могут совпадать.
     *
     * @param  array $ids - массив ID стран в необходимом порядке
     * @return void
     */
    public function setSortOrderTableProxy(array $ids, array $filters = array())
    {
        foreach($ids as &$id) {
            $id = intval($id);
        }

        if ($ids) {

            $table = $this->getTable();

            $q = Doctrine_Query::create()
                ->update($table->getClassnameToReturn())
                ->set('sort', sprintf("FIELD(`%s`, '%s')", $this->_options['key_column'], implode("','", $ids)))
                ->andWhereIn($this->_options['key_column'], $ids);

            foreach ($filters as $column => $value) {
                $q->andWhere("{$column} = ?", $value);
            }

            $q->execute();
        }
    }

}


/**
 * Listener
 */
class Doctrine_Template_exSortable_Listener extends Doctrine_Record_Listener
{

    /**
     * Добавляет в запрос критерий сортировки.
     * Только если это не подзапрос и еще не содержит критерии сортировки
     */
    public function preDqlSelect(Doctrine_Event $event)
    {
        $query = $event->getQuery();
        if (!$query->isSubquery() && !$query->contains('ORDER BY') && $query->getDqlPart('limit') != array(1)) {
            $params = $event->getParams();

            if (Doctrine_Template_exSortable::MODE_PREPEND == $this->getOption('mode')) {
                $query->orderBy(sprintf('%s.sort', $params['alias']));
            } else {

                $query->orderBy(sprintf('%s.sort, %s.%s DESC', $params['alias'], $params['alias'], $this->getOption('key_column')));
            }
        }
    }

}
