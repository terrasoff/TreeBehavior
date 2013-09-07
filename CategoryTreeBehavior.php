<?php

/**
 * @author terrasoff
 * @desc категория в виде дерева
 */

require_once dirname(__FILE__).'/TreeBehavior.php';

class CategoryTreeBehavior extends TreeBehavior
{

    /**
     * Получить подкатегорию дерева (не подветку)
     * @param bool $getData - сохранять данные категории (иначе - экземпляр AR)
     * @return string
     */
    public function getCategory($getData = true) {
        // все, что нашлось...
        $items = array();
        // список идентификаторов найденных категорий
        $list = array();
        $category = $this->getOwner();
        // ищем подкатегории
        $children = $category->findAll($this->getCategoryCriteria());
        if ($children) {
            // готовим данные для отображения рубрики
            if ($getData) {
                // формируем список идентификаторов
                foreach ($children as $item) $list[] = $item->id;
                // подписки пользователя (в пределах заданной подкатегории)
                $subscriptions = Yii::app()->user->isGuest || !count($list)
                    ? null
                    : $this->getSubscriptions($list);

                // формируем данные для каждой категории
                foreach ($children as $item) {
                    // формируем данные для отображения рубрики
                    $data = $item->getCategoryData();
                    // подписки в пределах очередной категории
                    $data['subscriptions'] = isset($subscriptions[$item->id])
                        ? $subscriptions[$item->id]
                        : null;

                    $items[] = $data;
                }
                // получаем категорию в виде массива AR (а не массивом)
            } else {
                foreach ($children as $item) $items[] = $item;
            }

        }
        return $items;
    }

    /**
     * Формируем данные для отображения категории
     * @params array $params параметры поиска
     * @return array
     */
    public function getCategoryData($params = null) {
        $category = $this->getOwner();
        $data = array();
        // пусть до картинки нужно сделать в любом случае абсолютным
        $data['id'] = $category->id;
        $data['name'] = $category->name;
        $data['path'] = $category->path;
        // ответ
        return $data;
    }

    /**
     * @desc получить id категории по ее названию
     * @param $name название категории
     * @return int id категории
     */
    public function getCategoryByName($name) {
        return $this->getOwner()->findAllByAttributes(array('name' => $name));
    }

    /**
     * Получаем информацию для выбранной категории о всей структуре от корня до категории с учетом вложенности
     * @return array формируем массив с данными (дочерние узлы соотв.ключу children)
     * @throws TException если что-то не так
     */
    public function getCategoryParentsData() {
        $data = $this->getCategory();
        $category = $this;
        while($parent_id = $category->parent_id) {
            $current_id = $category->id;
            $category = Category::model()->findByPk($category->parent_id);
            if (!$category)
                throw new TException('Ошибка в структуре рубрикатора');
            $items = $category->getCategory();
            foreach ($items as $i=>$item) {
                if ($item['id'] == $current_id) {
                    $items[$i]['children'] = $data;
                }
            }
            $data = $items;
        }

        return $data;
    }

    /**
     * Есть ли у элемента дети
     * @return bool
     */
    public function isLeaf() {
        return $this->getOwner()->has_children == 0;
    }

    public function getCacheKey() {
        return 'TreeBehavior.Node.'.$this->getOwner()->getId();
    }

    /**
     * Cписок идентификаторов подкатегорий
     * @return array
     */
    public function getFlatternList(){
        $list = $this->flattern();
        $list[] = $this->getOwner()->getId();
        return $list;
    }

    /*
     * Кешированный список идентификаторов подкатегорий
     * @param $cacheSettings настройки кеширования
     */
    public function getCachedFlatternList($cacheSettings){
        $cacheKey = $this->getCacheKey();
        $cache = Yii::app()->cache;
        if (!$data = $cache->get($cacheKey)) {
            $data = $this->getFlatternList();
            $cache->set($cacheKey,$data,
                (empty($cacheSettings['duration']) ? null : $cacheSettings['duration']),
                (empty($cacheSettings['dependency']) ? null : $cacheSettings['dependency'])
            );
        }
        return $data;
    }


}