<?php

/**
 * @author terrasoff
 * @desc категория в рубрикаторе
 */
class TreeBehavior extends CActiveRecordBehavior
{

    public $criteria = null;
    public $idAttribute = 'id';
    public $parentIdAttribute = 'parent_id';
    public $orderAttribute = 'weight';

    /**
     * Составляем путь до выбранной категории от корня с учетом вложенности
     * @return array
     * @throws TException
     */
    public function getPath() {
        // пока путь не определены
        $path = array();

        // выбранная категория
        if ($node = $this->getOwner())
        {
            // первый элемент
            $path[] = $node;
            // остальные элементы
            $parent_id = (int)$node->{$this->parentIdAttribute};
            if ($parent_id) {
                while ($parent_id) {
                    // очерденой родитель
                    $node = Category::model()->findByPk($parent_id);
                    $path[] = $node;
                    if (!$node)
                        throw new TException('Не удалось найти путь до заданной категории');
                    $parent_id = (int)$node->{$this->parentIdAttribute};
                }
            }
        }

        return $path;
    }

    public function getBreadcrumbs() {
        $breadcrumbs = array();
        $items = $this->getPath();
        if ($items) {
            foreach ($items as $i=>$item) {
                $breadcrumbs[] = $item->toArray();
            }
        }
        return array_reverse($breadcrumbs);
    }

    public function getNodeCriteria($id = null) {
        $criteria = new CDbCriteria();
        $node = $this->getOwner();

        if ($id) {
            $criteria->addCondition($this->parentIdAttribute.'=:id');
            $criteria->order = $this->orderAttribute;
            $criteria->params = array(':id'=>$id);
        } else {
            $criteria->addCondition($this->parentIdAttribute.'=0');
        }

        return $criteria;
    }

    /**
     * Получаем идентификаторы узлов из вложенного дерева узлов
     */
    public function flattern($items = null, &$list = array()) {
        // дочерние узлы
        $items = $items
            ? $items
            : $this->getTree();

        foreach ($items as $i=>$item) {
            $list[] = (int)$item[$this->idAttribute];
            if (is_array($item['children'])) {
                $this->flattern($item['children'],$list);
            }
        }
        
        return $list;
    }

    /**
     * Обходим дерево с заданной глубиной от определенного корня
     * @param int $depth глубина
     * @param null $root корень
     * @return array
     */
    public function getTree($depth = 4) {
        return (array)$this->walkTree($depth);
    }

    /**
     * Рекурсивный обход дерева с заданной глубиной по текущей глубине
     * @param int $depth глубина
     * @param null $current_depth текущая глубина
     * @return array
     */
    public function walkTree($depth = 4,$current_depth = 0) {
        $node = $this->getOwner();
        // глубже, чем надо не лезем
        if ($current_depth++ > $depth) return;

        // завершили обход?
        if ($this->criteria)
            $node->getDbCriteria()->mergeWith($this->criteria);

        $children = $node->findAll($this->getNodeCriteria($node->{$this->idAttribute}));
        if (!$children)
            return;

        $items = array();
        // продолжаем обход
        foreach($children as $n) {
            $item = $n->walkTree($depth,$current_depth);
            $items[] = $n->toArray($item);
        }
        return $items;
    }
}