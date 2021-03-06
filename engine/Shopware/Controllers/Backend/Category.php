<?php
/**
 * Shopware 4
 * Copyright © shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * Shopware Categories
 *
 * Backend Controller for the category backend module.
 * Displays all data in an Ext JS TreePanel and allows to delete,
 * add and edit items. On the detail page the category data is displayed and can be edited
 *
 * @category  Shopware
 * @package   Shopware\Controllers\Backend
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Controllers_Backend_Category extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * @var \Shopware\Models\Category\Repository
     */
    protected $repository;
    /**
     * @var \Shopware\Models\Article\Repository
     */
    protected $articleRepository = null;

    /**
     * @var \Shopware\Models\Media\Repository
     */
    protected $mediaRepository = null;

    /**
     * @var \Shopware\Models\Customer\Repository
     */
    protected $customerRepository = null;

    /**
     * Method to define acl dependencies in backend controllers
     * <code>
     * $this->addAclPermission("name_of_action_with_action_prefix","name_of_assigned_privilege","optionally error message");
     * // $this->addAclPermission("indexAction","read","Ops. You have no permission to view that...");
     * </code>
     */
    protected function initAcl()
    {
        // read
        $this->addAclPermission('getList', 'read', 'Insufficient Permissions');
        $this->addAclPermission('getDetail', 'read', 'Insufficient Permissions');
        $this->addAclPermission('getPathByQuery', 'read', 'Insufficient Permissions');
        $this->addAclPermission('getIdPath', 'read', 'Insufficient Permissions');
        $this->addAclPermission('getTemplateSettings', 'read', 'Insufficient Permissions');

        // update
        $this->addAclPermission('updateDetail', 'update', 'Insufficient Permissions');

        //delete
        $this->addAclPermission('delete', 'delete', 'Insufficient Permissions');

        // create
        $this->addAclPermission('createDetail', 'create', 'Insufficient Permissions');
    }

    /**
     * Helper Method to get access to the category repository.
     *
     * @return Shopware\Models\Category\Repository
     */
    public function getRepository()
    {
        if ($this->repository === null) {
            $this->repository = Shopware()->Models()->getRepository('Shopware\Models\Category\Category');
        }
        return $this->repository;
    }

    /**
     * Helper Method to get access to the media repository.
     *
     * @return \Shopware\Models\Media\Repository
     */
    private function getMediaRepository()
    {
        if ($this->mediaRepository === null) {
            $this->mediaRepository = Shopware()->Models()->getRepository('Shopware\Models\Media\Media');
        }
        return $this->mediaRepository;
    }

    /**
     * Helper Method to get access to the customer repository.
     *
     * @return \Shopware\Models\Article\Repository
     */
    private function getCustomerRepository()
    {
        if ($this->customerRepository === null) {
            $this->customerRepository = Shopware()->Models()->getRepository('Shopware\Models\Customer\Customer');
        }
        return $this->customerRepository;
    }

    /**
     * @return \Shopware\Components\Model\CategoryDenormalization
     */
    public function getCategoryComponent()
    {
        return Shopware()->CategoryDenormalization();
    }

    /**
     * Reads all known categories into an array to show it in the category treepanel
     */
    public function getListAction()
    {
        /** @var $filter array */
        $filter = $this->Request()->getParam('filter', array());
        $node = (int) $this->Request()->getParam('node');
        $preselectedNodes = $this->Request()->getParam('preselected');

        if (empty($filter)) {
            $node = !empty($node) ? $node : 1;
            $filter[] = array('property' => 'c.parentId', 'value' => $node);
        }

        $query = $this->getRepository()->getBackendListQuery(
            $filter,
            $this->Request()->getParam('sort', array()),
            $this->Request()->getParam('limit', null),
            $this->Request()->getParam('start')
        )->getQuery();

        $count = Shopware()->Models()->getQueryCount($query);

        $data = $query->getArrayResult();

        foreach ($data as $key => $category) {
            $data[$key]['text'] = $category['name'];
            $data[$key]['cls'] = 'folder';
            $data[$key]['childrenCount'] = (int) $category['childrenCount'];
            $data[$key]['leaf'] = empty($data[$key]['childrenCount']);
            $data[$key]['allowDrag'] = true;
            if ($preselectedNodes !== null) {
                $data[$key]['checked'] = in_array($category['id'], $preselectedNodes);
            }
        }

        $this->View()->assign(array(
            'success' => true, 'data' => $data, 'total' => $count
        ));
    }


    /**
     * Controller action which can be accessed over an request.
     * This function adds the passed article ids which have to be in the "ids" parameter
     * to the passed category.
     */
    public function addCategoryArticlesAction()
    {
        $this->View()->assign(
            $this->addCategoryArticles(
                $this->Request()->getParam('categoryId'),
                json_decode($this->Request()->getParam('ids'))
            )
        );
    }

    /**
     * Controller action which can be accessed over an request.
     * This function adds the passed article ids which have to be in the "ids" parameter
     * to the passed category.
     */
    public function removeCategoryArticlesAction()
    {
        $this->View()->assign(
            $this->removeCategoryArticles(
                $this->Request()->getParam('categoryId'),
                json_decode($this->Request()->getParam('ids'))
            )
        );
    }

    /**
     * Internal function which is used to remove the passed article ids
     * from the assigned category.
     *
     * @param int $categoryId
     * @param array $articleIds
     * @return array
     */
    protected function removeCategoryArticles($categoryId, $articleIds)
    {
        if (empty($articleIds)) {
            return array('success' => false, 'error' => 'No articles selected');
        }

        if (empty($categoryId)) {
            return array('success' => false, 'error' => 'No category id passed.');
        }

        /** @var \Shopware\Models\Category\Category $category */
        $category = Shopware()->Models()->getReference('Shopware\Models\Category\Category', $categoryId);

        $counter = 0;
        foreach ($articleIds as $articleId) {
            if (empty($articleId)) {
                continue;
            }

            /** @var \Shopware\Models\Article\Article $article */
            $article = Shopware()->Models()->getReference('Shopware\Models\Article\Article', (int) $articleId);
            $article->removeCategory($category);

            $counter++;
        }

        Shopware()->Models()->flush();

        return array('success' => true, 'counter' => $counter);
    }

    /**
     * Helper function to add multiple articles to an category.
     *
     * @param int $categoryId
     * @param array $articleIds
     * @return array
     */
    protected function addCategoryArticles($categoryId, $articleIds)
    {
        if (empty($articleIds)) {
            return array('success' => false, 'error' => 'No articles selected');
        }

        if (empty($categoryId)) {
            return array('success' => false, 'error' => 'No category id passed.');
        }

        /** @var \Shopware\Models\Category\Category $category */
        $category = Shopware()->Models()->getReference('Shopware\Models\Category\Category', $categoryId);

        $counter = 0;
        foreach ($articleIds as $articleId) {
            if (empty($articleId)) {
                continue;
            }

            /** @var \Shopware\Models\Article\Article $article */
            $article = Shopware()->Models()->getReference('Shopware\Models\Article\Article', (int) $articleId);
            $article->addCategory($category);

            $counter++;
        }

        Shopware()->Models()->flush();

        return array('success' => true, 'counter' => $counter);
    }

    /**
     * Gets all category detail information by the category node
     */
    public function getDetailAction()
    {
        $node = $this->Request()->getParam('node', 1);
        if ($node !== null) {
            $node = is_numeric($node) ? (int) $node : 1;
            $filter[] = array('property' => 'c.parentId', 'value' => $node);
        }
        $query = $this->getRepository()->getBackendDetailQuery($node)->getQuery();
        $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        $paginator = $this->getModelManager()->createPaginator($query);
        $data = $paginator->getIterator()->getArrayCopy();
        $data = $data[0];

        $data["imagePath"] = $data["media"]["path"];

        $this->View()->assign(array('success' => true, 'data' => $data));
    }

    /**
     * Returns the whole category path by an category id
     */
    public function getPathByQueryAction()
    {
        $separator = $this->Request()->getParam('separator', '>');

        if (($ids = $this->Request()->getParam('id')) !== null) {
            $result = array();
            if (is_string($ids)) {
                $ids = explode(', ', $ids);
            }
            foreach ($ids as $id) {
                $result[] = $this->getRepository()->getPathById($id, 'name', $separator);
            }
        } else {
            $query = $this->Request()->getParam('query');
            $parents = (bool) $this->Request()->getParam('parents', false);
            $result = $this->getPathByQuery($query, $separator, $parents);
        }

        $data = array();
        foreach ($result as $id => $name) {
            $data[] = array('id' => $id, 'name' => $name);
        }

        $this->View()->assign(array('success' => true, 'data' => $data, 'total' => count($data)));
    }

    /**
     * Reads the id paths for the given category ids
     */
    public function getIdPathAction()
    {
        $separator = $this->Request()->getParam('separator', '/');
        $categoryIds = (array) $this->Request()->getParam('categoryIds', array());

        $data = array();
        if (empty($categoryIds)) {
            $categoryIds = array('1');
        }
        foreach ($categoryIds as $categoryId) {
            $data[] = $separator . $this->getRepository()->getPathById($categoryId, 'id', $separator);
        }

        $this->View()->assign(array('success' => true, 'data' => $data, 'total' => count($data)));
    }

    /**
     * helper method used in the getPathByQueryAction to return the path information
     *
     * @param null $query
     * @param string $separator
     * @param bool $parents
     * @return array
     */
    public function getPathByQuery($query = null, $separator = '>', $parents = false)
    {
        if (empty($query)) {
            $where = 'parent=1';
        } elseif (is_numeric($query)) {
            $where = 'parent=' . (int) $query;
        } else {
            $where = 'description LIKE ' . Shopware()->Db()->quote('%' . trim($query) . '%');
        }

        $paths = array();

        $sql = '
            SELECT id, description as name, parent
            FROM s_categories
            WHERE ' . $where . '
            ORDER BY parent DESC, name
        ';
        $result = Shopware()->Db()->fetchAll($sql);
        if (!empty($result)) {
            foreach ($result as $category) {
                if (!empty($query) && !is_numeric($query) && $category['parent'] != 1) {
                    $category['name'] = $this->getRepository()->getPathById($category['id'], 'name', $separator);
                }
                $children = $this->getPathByQuery($category['id'], $separator, $parents);
                if (empty($children) || $parents) {
                    $paths[$category['id']] = $category['name'];
                }
                if (!empty($children)) {
                    foreach ($children as $key => $child) {
                        $paths[$key] = $category['name'] . $separator . $child;
                    }
                }
            }
        }
        return $paths;
    }

    /**
     * Reads the Config categoryTemplate and returns the settings entries for the category templates
     */
    public function getTemplateSettingsAction()
    {
        $categoryTemplates = explode(';', Shopware()->Config()->categoryTemplates);
        $data = array();
        foreach ($categoryTemplates as $templateConfigRaw) {
            list($template, $name) = explode(':', $templateConfigRaw);
            $data[] = array('template' => $template, 'name' => $name);
        }
        $this->View()->assign(array('success' => true, 'data' => $data, 'total' => count($data)));
    }

    /**
     * Wrapper around the save method to have better ACL control
     */
    public function updateDetailAction()
    {
        $this->saveDetail();
    }

    /**
     * Wrapper around the save method to have better ACL control
     */
    public function createDetailAction()
    {
        $this->saveDetail();
    }

    /**
     * returns a JSON string to the view the articles for the article mapping
     *
     * @return void
     */
    public function getArticlesAction()
    {
        $categoryId = $this->Request()->getParam('categoryId', 0);
        $offset = $this->Request()->getParam('start', 0);
        $limit = $this->Request()->getParam('limit', 20);
        $search = $this->Request()->getParam('search', '');
        $conditions = '';

        if (!empty($search)) {
            $search = '%' . $search . '%';
            $conditions = "
            AND (
                   s_articles.name LIKE '".$search."'
                OR s_articles_details.ordernumber LIKE '".$search."'
                OR s_articles_supplier.name LIKE '".$search."'
            )
            ";
        }

        $sql = "
            SELECT SQL_CALC_FOUND_ROWS
                s_articles.id as articleId,
                s_articles.name,
                s_articles_details.ordernumber as number,
                s_articles_supplier.name as supplierName
            FROM s_articles
               INNER JOIN s_articles_details
                 ON s_articles_details.id = s_articles.main_detail_id
               INNER JOIN s_articles_supplier
                 ON s_articles.supplierID = s_articles_supplier.id
               LEFT JOIN s_articles_categories
                 ON s_articles.id = s_articles_categories.articleID
                 AND s_articles_categories.categoryID = :categoryId
            WHERE s_articles_categories.id IS NULL
            " . $conditions . "
            LIMIT $offset , $limit
        ";

        $result = Shopware()->Db()->fetchAll($sql, array(
            'categoryId' => (int) $categoryId
        ));

        $sql= "SELECT FOUND_ROWS() as count";
        $count = Shopware()->Db()->fetchOne($sql);

        $this->View()->assign(array(
            'success' => true,
            'data' => $result,
            'total' => $count
        ));
    }

    /**
     * Controller action which is used to get a paginated
     * list of all assigned category articles.
     */
    public function getCategoryArticlesAction()
    {
        $categoryId = $this->Request()->getParam('categoryId', null);
        $offset = $this->Request()->getParam('start', 0);
        $limit = $this->Request()->getParam('limit', 20);
        $search = $this->Request()->getParam('search', '');

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(array(
            'articles.id as articleId',
            'articles.name',
            'details.number',
            'suppliers.name as supplierName'
        ));
        $builder->from('Shopware\Models\Article\Article', 'articles')
                ->innerJoin('articles.categories', 'categories')
                ->innerJoin('articles.supplier', 'suppliers')
                ->innerJoin('articles.mainDetail', 'details')
                ->where('categories.id = :categoryId')
                ->setParameters(array('categoryId' => $categoryId));

        if (!empty($search)) {
            $builder->andWhere('(articles.name LIKE :search OR suppliers.name LIKE :search OR details.number LIKE :search)');
            $builder->setParameter('search', '%' . $search . '%');
        }

        $builder->setFirstResult($offset)
                ->setMaxResults($limit);

        $query = $builder->getQuery();

        $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = $this->getModelManager()->createPaginator($query);

        $data = $paginator->getIterator()->getArrayCopy();
        $count = $paginator->count();

        $this->View()->assign(array(
            'success' => true,
            'data' => $data,
            'total' => $count
        ));
    }

    /**
     * returns a JSON string to the view the customers for the customer group mapping
     *
     * @return void
     */
    public function getCustomerGroupsAction()
    {
        $usedIds = $this->Request()->usedIds;

        $offset = $this->Request()->getParam('start', null);
        $limit = $this->Request()->getParam('limit', 20);

        /** @var $customerRepository \Shopware\Models\Customer\Repository */
        $customerRepository = $this->getCustomerRepository();
        $dataQuery = $customerRepository->getCustomerGroupsWithoutIdsQuery($usedIds, $offset, $limit);

        $total = Shopware()->Models()->getQueryCount($dataQuery);
        $data = $dataQuery->getArrayResult();

        //return the data and total count
        $this->View()->assign(array('success' => true, 'data' => $data, 'total' => $total));
    }

    /**
     * moves a category by the categoryId under a new parent node
     */
    public function moveTreeItemAction()
    {
        $itemId     = (int) $this->Request()->getParam('id');
        $parentId   = (int) $this->Request()->getParam('parentId', 1);

        /** @var $item \Shopware\Models\Category\Category */
        $item = $this->getRepository()->find($itemId);
        if ($item === null) {
            $this->View()->assign(array(
                'success' => false,
                'message' => "Category by id $itemId not found"
            ));

            return;
        }

        /** @var $parent \Shopware\Models\Category\Category */
        $parent = $this->getRepository()->find($parentId);
        if ($parent === null) {
            $this->View()->assign(array(
                'success' => false,
                'message' => "Parent by id $parentId not found"
            ));

            return;
        }

        $needsRebuild = false;

        if ($item->getParent()->getId() != $parent->getId()) {
            $item->setParent($parent);

            $parents = $this->getCategoryComponent()->getParentCategoryIds($parentId);

            $path = implode('|', $parents);
            if (empty($path)) {
                $path = null;
            } else {
                $path = '|' . $path . '|';
            }

            $item->internalSetPath($path);

            $batchModeEnabled = Shopware()->Config()->get('moveBatchModeEnabled');

            if ($item->isLeaf() || !$batchModeEnabled) {
                $needsRebuild = false;
            } else {
                Shopware()->CategorySubscriber()->disableForNextFlush();
                $needsRebuild = true;
            }

            Shopware()->Models()->flush($item);
        }

        $this->View()->assign(array(
            'success'      => true,
            'needsRebuild' => $needsRebuild,
        ));
    }

    /**
     * saves the positions of all children of this parent category
     */
    public function saveNewChildPositionsAction()
    {
        $ids = json_decode($this->Request()->getParam('ids'));
        foreach ($ids as $key => $categoryId) {
            /** @var $category \Shopware\Models\Category\Category */
            $category = Shopware()->Models()->getReference('Shopware\Models\Category\Category', $categoryId);
            $category->setPosition($key);
        }
        Shopware()->Models()->flush();

        $this->View()->assign(array( 'success' => true));
    }

    /**
     * Saves a single category. If no category id is passed,
     * the save function will create a new category model and persist
     * it.
     *
     * To successful saving a category a parent category id must supplied.
     */
    public function saveDetail()
    {
        try {
            $params = $this->Request()->getParams();
            $categoryId = $params['id'];

            if (empty($categoryId)) {
                $categoryModel = new \Shopware\Models\Category\Category();
                Shopware()->Models()->persist($categoryModel);

                // Find parent for newly created category
                $params['parentId'] = is_numeric($params['parentId']) ? (int) $params['parentId'] : 1;
                $parentCategory = $this->getRepository()->find($params['parentId']);
                $categoryModel->setParent($parentCategory);

                // If Leaf-Category gets childcategory move all assignments to new childcategory
                if ($parentCategory->getChildren()->count() === 0 && $parentCategory->getArticles()->count() > 0) {
                    /** @var $article \Shopware\Models\Article\Article **/
                    foreach ($parentCategory->getArticles() as $article) {
                        $article->removeCategory($parentCategory);
                        $article->addCategory($categoryModel);
                    }
                }
            } else {
                $categoryModel = $this->getRepository()->find($categoryId);
            }

            $params = $this->prepareAttributeAssociatedData($params);
            $params = $this->prepareCustomerGroupsAssociatedData($params);
            $params = $this->prepareMediaAssociatedData($params);

            unset($params['articles']);
            unset($params['emotion']);
            unset($params['imagePath']);
            unset($params['parentId']);
            unset($params['parent']);

            $categoryModel->fromArray($params);

            Shopware()->Models()->flush();

            $categoryId = $categoryModel->getId();
            $query = $this->getRepository()->getBackendDetailQuery($categoryId)->getQuery();
            $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
            $paginator = $this->getModelManager()->createPaginator($query);
            $data = $paginator->getIterator()->getArrayCopy();
            $data = $data[0];
            $data["imagePath"] = $data["media"]["path"];

            $this->View()->assign(array('success' => true, 'data' => $data, 'total' => count($data)));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    /**
     * Deletes a category and its children
     */
    public function deleteAction()
    {
        try {
            if (!($id = $this->Request()->getParam('id'))) {
                $this->View()->assign(array('success' => false, 'message' => 'No valid form Id'));
                return;
            }
            $result = $this->getRepository()->find($id);
            if (!$result) {
                $this->View()->assign(array('success' => false, 'message' => 'Category not found'));
                return;
            }

            // Doctrine removes all child-categories and assignments of parent and child categories
            Shopware()->Models()->remove($result);
            Shopware()->Models()->flush();

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    /**
     * This method loads the customer group models for the passed ids in the "customerGroups" parameter.
     *
     * @param $data
     * @return array
     */
    private function prepareCustomerGroupsAssociatedData($data)
    {
        $customerGroups = array();
        foreach ($data['customerGroups'] as $customerGroupData) {
            if (!empty($customerGroupData['id'])) {
                $model = Shopware()->Models()->find('Shopware\Models\Customer\Group', $customerGroupData['id']);
                $customerGroups[] = $model;
            }
        }
        $data['customerGroups'] = $customerGroups;

        return $data;
    }

    /**
     * This method loads the article models for the passed ids in the "articles" parameter.
     *
     * @param $data
     * @return array
     */
    protected function prepareAttributeAssociatedData($data)
    {
        $data['attribute'] = $data['attribute'][0];
        return $data;
    }

    /**
     * This method finds the mediaId by the media path to save it in the right way
     *
     * @param $data
     * @return array
     */
    protected function prepareMediaAssociatedData($data)
    {
        if (!empty($data["imagePath"])) {
            $mediaQuery = $this->getMediaRepository()->getMediaByPathQuery($data["imagePath"]);
            $mediaModel = $mediaQuery->getOneOrNullResult();
            $data["media"] = $mediaModel;
        } else {
            $data["media"] = null;
        }

        return $data;
    }


    public function getRebuildCategoryPathCountAction()
    {
        $categoryId = $this->Request()->getParam('categoryId');

        $count = $this->getCategoryComponent()->rebuildCategoryPathCount($categoryId);

        $this->view->assign(array(
            'success' => true,
            'data' => array(
                'count'     => $count,
                'batchSize' => 20
            )
        ));
    }

    public function rebuildCategoryPathAction()
    {
        // try to set maximum execution time
        @set_time_limit(0);

        $categoryId = $this->Request()->getParam('categoryId');
        $offset     = $this->Request()->getParam('offset');
        $count      = $this->Request()->getParam('limit');

        $this->getCategoryComponent()->rebuildCategoryPath($categoryId, $count, $offset);

        $this->view->assign(array(
            'success' => true,
        ));
    }

    public function getRemoveOldAssignmentsCountAction()
    {
        $categoryId = $this->Request()->getParam('categoryId');

        $count = $this->getCategoryComponent()->removeOldAssignmentsCount($categoryId);

        $this->view->assign(array(
            'success' => true,
            'data' => array(
                'count'     => $count,
                'batchSize' => 1
            )
        ));
    }

    public function removeOldAssignmentsAction()
    {
        // try to set maximum execution time
        @set_time_limit(0);

        $categoryId = $this->Request()->getParam('categoryId');
        $offset     = $this->Request()->getParam('offset');
        $count      = $this->Request()->getParam('limit');

        $this->getCategoryComponent()->removeOldAssignments($categoryId, $count, $offset);

        $this->view->assign(array(
            'success' => true,
        ));
    }

    public function getRebuildAssignmentsCountAction()
    {
        $categoryId = $this->Request()->getParam('categoryId');

        $count = $this->getCategoryComponent()->rebuildAssignmentsCount($categoryId);

        $this->view->assign(array(
            'success' => true,
            'data' => array(
                'count'     => $count,
                'batchSize' => 1
            )
        ));
    }

    public function rebuildAssignmentsAction()
    {
        // try to set maximum execution time
        @set_time_limit(0);

        $categoryId = $this->Request()->getParam('categoryId');
        $offset     = $this->Request()->getParam('offset');
        $count      = $this->Request()->getParam('limit');

        $this->getCategoryComponent()->rebuildAssignments($categoryId, $count, $offset);

        $this->view->assign(array(
            'success' => true,
        ));
    }
}
