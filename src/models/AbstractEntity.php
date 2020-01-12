<?php
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Models;

use Elabftw\Elabftw\Db;
use Elabftw\Elabftw\Permissions;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Services\Check;
use Elabftw\Services\Email;
use Elabftw\Services\Filter;
use Elabftw\Traits\EntityTrait;
use PDO;

/**
 * The mother class of Experiments and Database
 */
abstract class AbstractEntity
{
    use EntityTrait;

    /** @var Comments $Comments instance of Comments */
    public $Comments;

    /** @var Links $Links instance of Links */
    public $Links;

    /** @var Steps $Steps instance of Steps */
    public $Steps;

    /** @var Tags $Tags instance of Tags */
    public $Tags;

    /** @var Uploads $Uploads instance of Uploads */
    public $Uploads;

    /** @var Users $Users our user */
    public $Users;

    /** @var string $type experiments or items */
    public $type = '';

    /** @var string $page will be defined in children classes */
    public $page = '';

    /** @var string $idFilter inserted in sql */
    public $idFilter = '';

    /** @var string $useridFilter inserted in sql */
    public $useridFilter = '';

    /** @var string $bookableFilter inserted in sql */
    public $bookableFilter = '';

    /** @var string $ratingFilter inserted in sql */
    public $ratingFilter = '';

    /** @var string $visibilityFilter inserted in sql */
    public $visibilityFilter = '';

    /** @var string $titleFilter inserted in sql */
    public $titleFilter = '';

    /** @var string $dateFilter inserted in sql */
    public $dateFilter = '';

    /** @var string $bodyFilter inserted in sql */
    public $bodyFilter = '';

    /** @var string $categoryFilter inserted in sql */
    public $categoryFilter = '';

    /** @var string $tagFilter inserted in sql */
    public $tagFilter = '';

    /** @var string $queryFilter inserted in sql */
    public $queryFilter = '';

    /** @var string $order inserted in sql */
    public $order = 'date';

    /** @var string $sort inserted in sql */
    public $sort = 'DESC';

    /** @var string $limit limit for sql */
    public $limit = '';

    /** @var string $offset offset for sql */
    public $offset = '';

    /** @var bool $isReadOnly if we can read but not write to it */
    public $isReadOnly = false;

    /** @var int $itemsReadNb the total number of items read from sql query */
    public $itemsReadNb;

    /**
     * Constructor
     *
     * @param Users $users
     * @param int|null $id the id of the entity
     */
    public function __construct(Users $users, ?int $id = null)
    {
        $this->Db = Db::getConnection();

        $this->Links = new Links($this);
        $this->Steps = new Steps($this);
        $this->Tags = new Tags($this);
        $this->Uploads = new Uploads($this);
        $this->Users = $users;
        $this->Comments = new Comments($this, new Email(new Config(), $this->Users));
        $this->itemsReadNb = 0;

        if ($id !== null) {
            $this->setId($id);
        }
    }

    /**
     * Create an empty entry
     *
     * @param int $tpl a template/category
     * @return int the new id
     */
    abstract public function create(int $tpl): int;

    /**
     * Duplicate an item
     *
     * @return int the new item id
     */
    abstract public function duplicate(): int;

    /**
     * Destroy an item
     *
     * @return void
     */
    abstract public function destroy(): void;

    /**
     * Lock/unlock
     *
     * @return void
     */
    public function toggleLock(): void
    {
        // no locking for templates
        if ($this instanceof Templates) {
            return;
        }

        $permissions = $this->getPermissions();
        if (!$this->Users->userData['can_lock'] && !$permissions['write']) {
            throw new ImproperActionException(_("You don't have the rights to lock/unlock this."));
        }
        $locked = (int) $this->entityData['locked'];

        // if we try to unlock something we didn't lock
        if ($locked === 1 && ($this->entityData['lockedby'] != $this->Users->userData['userid'])) {
            // Get the first name of the locker to show in error message
            $sql = 'SELECT firstname FROM users WHERE userid = :userid';
            $req = $this->Db->prepare($sql);
            $req->bindParam(':userid', $this->entityData['lockedby'], PDO::PARAM_INT);
            if ($req->execute() !== true) {
                throw new DatabaseErrorException('Error while executing SQL query.');
            }
            $firstname = $req->fetchColumn();
            if ($firstname === false || $firstname === null) {
                throw new ImproperActionException('Could not find the firstname of the locker!');
            }
            throw new ImproperActionException(
                sprintf(_("This experiment was locked by %s. You don't have the rights to unlock this."), $firstname)
            );
        }

        // check if the experiment is timestamped. Disallow unlock in this case.
        if ($locked === 1 && $this->entityData['timestamped'] && $this instanceof Experiments) {
            throw new ImproperActionException(_('You cannot unlock or edit in any way a timestamped experiment.'));
        }

        $sql = 'UPDATE ' . $this->type . ' SET locked = IF(locked = 1, 0, 1), lockedby = :lockedby, lockedwhen = CURRENT_TIMESTAMP WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':lockedby', $this->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Read all from the entity
     * Optionally with filters
     * Here be dragons!
     *
     * @param bool $getTags if true, might take a very long time, false in show mode
     * @param bool $inTeam, set to false to disable team filtering
     *
     * @return array
     */
    public function read(bool $getTags = true, bool $inTeam = true): array
    {
        if ($this->id !== null) {
            $this->idFilter = ' AND ' . $this->type . '.id = ' . (string) $this->id;
        }

        $uploadsJoin = 'LEFT JOIN (
            SELECT uploads.item_id AS up_item_id,
                (uploads.item_id IS NOT NULL) AS has_attachment,
                uploads.type
            FROM uploads
            GROUP BY uploads.item_id, uploads.type)
            AS uploads
            ON (uploads.up_item_id = ' . $this->type . ".id AND uploads.type = '" . $this->type . "')";

        $tagsSelect = ", GROUP_CONCAT(DISTINCT tags.tag ORDER BY tags.id SEPARATOR '|') as tags, GROUP_CONCAT(DISTINCT tags.id) as tags_id";
        $tagsJoin = 'LEFT JOIN tags2entity ON (' . $this->type . ".id = tags2entity.item_id AND tags2entity.item_type = '" . $this->type . "') LEFT JOIN tags ON (tags2entity.tag_id = tags.id)";

        if ($this instanceof Experiments) {
            $select = 'SELECT DISTINCT ' . $this->type . ".*,
                status.color, status.name AS category, status.id AS category_id,
                uploads.up_item_id, uploads.has_attachment,
                experiments_comments.recent_comment,
                (experiments_comments.recent_comment IS NOT NULL) AS has_comment,
                SUBSTRING_INDEX(GROUP_CONCAT(stepst.next_step SEPARATOR '|'), '|', 1) AS next_step,
                CONCAT(users.firstname, ' ', users.lastname) AS fullname";

            $from = 'FROM experiments';

            $usersJoin = 'LEFT JOIN users ON (experiments.userid = users.userid)';
            $stepsJoin = 'LEFT JOIN (
                SELECT experiments_steps.item_id AS steps_item_id,
                experiments_steps.body AS next_step,
                experiments_steps.finished AS finished
                FROM experiments_steps)
                AS stepst ON (
                experiments.id = steps_item_id
                AND stepst.finished = 0)';

            $statusJoin = 'LEFT JOIN status ON (status.id = experiments.category)';
            $commentsJoin = 'LEFT JOIN (
                SELECT MAX(experiments_comments.datetime) AS recent_comment,
                    experiments_comments.item_id FROM experiments_comments GROUP BY experiments_comments.item_id
                ) AS experiments_comments
                ON (experiments_comments.item_id = experiments.id)';

            $sql = $select . ' ';
            if ($getTags) {
                $sql .= $tagsSelect . ' ';
            }
            $sql .= $from . ' ' .
            $usersJoin . ' ' .
            $stepsJoin . ' ';
            if ($getTags) {
                $sql .= $tagsJoin . ' ';
            }
            $sql .= $statusJoin . ' ' .
            $uploadsJoin . ' ' .
            $commentsJoin;
        } elseif ($this instanceof Database) {
            $sql = "SELECT DISTINCT items.*, items_types.name AS category,
                items_types.color,
                items_types.id AS category_id,
                items_types.bookable,
                items_comments.recent_comment,
                (items_comments.recent_comment IS NOT NULL) AS has_comment,
                SUBSTRING_INDEX(GROUP_CONCAT(stepst.next_step SEPARATOR '|'), '|', 1) AS next_step,
                uploads.up_item_id, uploads.has_attachment,
                CONCAT(users.firstname, ' ', users.lastname) AS fullname";

            $from = 'FROM items
                LEFT JOIN items_types ON (items.category = items_types.id)
                LEFT JOIN users ON (users.userid = items.userid)';

            $stepsJoin = 'LEFT JOIN (
                SELECT items_steps.item_id AS steps_item_id,
                items_steps.body AS next_step,
                items_steps.finished AS finished
                FROM items_steps)
                AS stepst ON (
                items.id = steps_item_id
                AND stepst.finished = 0)';

            $commentsJoin = 'LEFT JOIN (
                SELECT MAX(items_comments.datetime) AS recent_comment,
                    items_comments.item_id FROM items_comments GROUP BY items_comments.item_id
                ) AS items_comments
                ON (items_comments.item_id = items.id)';

            $sql .= ' ';
            if ($getTags) {
                $sql .= $tagsSelect . ' ';
            }
            $sql .= $from . ' ' . $uploadsJoin . ' ' . $stepsJoin . ' ' . $commentsJoin . ' ';
            if ($getTags) {
                $sql .= $tagsJoin . ' ';
            }
        } else {
            throw new IllegalActionException('Nope.');
        }

        if ($this->id === null && $inTeam === true) {
            $sql .= ' WHERE ' . $this->type . '.team = :team';
        } else {
            $sql .= ' WHERE 1=1';
        }

        $sql .= $this->idFilter . ' ' .
            $this->useridFilter . ' ' .
            $this->titleFilter . ' ' .
            $this->dateFilter . ' ' .
            $this->bodyFilter . ' ' .
            $this->bookableFilter . ' ' .
            $this->categoryFilter . ' ' .
            $this->queryFilter . ' ' .
            $this->ratingFilter . ' ' .
            $this->visibilityFilter . ' ' .
            ' GROUP BY id ' . ' ' .
            $this->tagFilter . ' ' .
            'ORDER BY ' . $this->order . ' ' . $this->sort . ', ' . $this->type . '.id ' . $this->sort . ' ' . $this->limit . ' ' . $this->offset;

        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Users->userData['team'], PDO::PARAM_INT);
        $req->execute();

        $itemsArr = $req->fetchAll();
        if ($itemsArr === false) {
            $itemsArr = array();
        }
        // store the total number of items read from db
        $this->itemsReadNb = count($itemsArr);

        // loop the array and only add the ones we can read to return to template
        $finalArr = array();
        foreach ($itemsArr as $item) {
            $permissions = $this->getPermissions($item);
            if ($permissions['read']) {
                $finalArr[] = $item;
            }
        }

        // reduce the dimension of the array if we have only one item (idFilter set)
        if (count($itemsArr) === 1 && !empty($this->idFilter)) {
            return $itemsArr[0];
        }
        return $finalArr;
    }

    /**
     * Read the tags of the entity
     *
     * @param int $id id of the entity
     *
     * @return array
     */
    public function getTags(int $id): array
    {
        $sql = 'SELECT DISTINCT tags2entity.tag_id, tags.tag FROM tags2entity
            LEFT JOIN tags ON (tags2entity.tag_id = tags.id)
            WHERE tags2entity.item_id = :id and tags2entity.item_type = :type';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $id, PDO::PARAM_INT);
        $req->bindParam(':type', $this->type);
        $req->execute();
        $res = $req->fetchAll();
        if ($res === false) {
            return array();
        }
        return $res;
    }

    /**
     * Update an entity. The revision is saved before so it can easily compare old and new body.
     *
     * @param string $title
     * @param string $date
     * @param string $body
     * @throws ImproperActionException
     * @throws DatabaseErrorException
     * @return void
     */
    public function update(string $title, string $date, string $body): void
    {
        $this->canOrExplode('write');

        // don't update if locked
        if ($this->entityData['locked']) {
            throw new ImproperActionException(_('Cannot update a locked entity!'));
        }

        // add a revision
        $Revisions = new Revisions($this);
        $Revisions->create($body);

        $title = Filter::title($title);
        $date = Filter::kdate($date);
        $body = Filter::body($body);

        if ($this instanceof Experiments) {
            $sql = 'UPDATE experiments SET
                title = :title,
                date = :date,
                body = :body
                WHERE id = :id';
        } else {
            $sql = 'UPDATE items SET
                title = :title,
                date = :date,
                body = :body,
                userid = :userid
                WHERE id = :id';
        }

        $req = $this->Db->prepare($sql);
        $req->bindParam(':title', $title);
        $req->bindParam(':date', $date);
        $req->bindParam(':body', $body);
        if ($this instanceof Database) {
            // if we are the admin doing an edit on a visibility = user item, we don't want to change the userid
            // first get the visibility
            $sql = 'SELECT userid, visibility FROM items WHERE id = :id';
            $req2 = $this->Db->prepare($sql);
            $req2->bindParam(':id', $this->id, PDO::PARAM_INT);
            if ($req2->execute() !== true) {
                throw new DatabaseErrorException('Error while executing SQL query.');
            }
            $item = $req2->fetch();

            $newUserid = $this->Users->userData['userid'];
            if ($item['visibility'] === 'user') {
                $newUserid = $item['userid'];
            }
            $req->bindParam(':userid', $newUserid, PDO::PARAM_INT);
        }
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Set a limit for sql read. The limit is two times the wanted number of
     * displayed results so we can remove the ones without read access
     *
     * @param int $num number of items to ignore
     * @return void
     */
    public function setLimit(int $num): void
    {
        $num *= 2;
        $this->limit = 'LIMIT ' . (string) $num;
    }

    /**
     * Add an offset to the displayed results
     *
     * @param int $num number of items to ignore
     * @return void
     */
    public function setOffset(int $num): void
    {
        $this->offset = 'OFFSET ' . (string) $num;
    }

    /**
     * Set the userid filter for read()
     *
     * @return void
     */
    public function setUseridFilter(): void
    {
        $this->useridFilter = ' AND ' . $this->type . '.userid = ' . $this->Users->userData['userid'];
    }

    /**
     * Update the visibility for an entity
     *
     * @param string $visibility
     * @return void
     */
    public function updateVisibility(string $visibility): void
    {
        Check::visibility($visibility);
        $this->canOrExplode('write');

        $sql = 'UPDATE ' . $this->type . ' SET visibility = :visibility WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':visibility', $visibility);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException($sql);
        }
    }

    /**
     * If int, get the name of the team group instead of a number
     *
     * @return string
     */
    public function getVisibility(): string
    {
        $TeamGroups = new TeamGroups($this->Users);
        if (Check::id((int) $this->entityData['visibility']) !== false) {
            return $TeamGroups->readName((int) $this->entityData['visibility']);
        }
        return ucfirst($this->entityData['visibility']);
    }

    /**
     * Check if we have the permission to read/write or throw an exception
     *
     * @param string $rw read or write
     * @throws IllegalActionException
     * @return void
     */
    public function canOrExplode(string $rw): void
    {
        $permissions = $this->getPermissions();

        // READ ONLY?
        if ($permissions['read'] && !$permissions['write']) {
            $this->isReadOnly = true;
        }

        if (!$permissions[$rw]) {
            throw new IllegalActionException('User tried to access entity without permission.');
        }
    }

    /**
     * Verify we can read/write an item
     * Here be dragons! Cognitive load > 9000
     *
     * @param array|null $item one item array
     * @return array
     */
    public function getPermissions(?array $item = null): array
    {
        if (!isset($this->entityData) && !isset($item)) {
            $this->populate();
            if (!isset($this->entityData)) {
                return array('read' => false, 'write' => false);
            }
        }
        // don't try to read() again if we have the item (for show where there are several items to check)
        if (!isset($item)) {
            $item = $this->entityData;
        }

        $Permissions = new Permissions($this->Users, $item);

        if ($this instanceof Experiments) {
            return $Permissions->forExperiments();
        }

        if ($this instanceof Templates) {
            return $Permissions->forTemplates();
        }

        if ($this instanceof Database) {
            return $Permissions->forDatabase();
        }
        return array('read' => false, 'write' => false);
    }

    /**
     * Get a list of experiments with title starting with $term and optional user filter
     *
     * @param string $term the query
     * @return array
     */
    public function getExpList(string $term): array
    {
        $Experiments = new Experiments($this->Users);
        $Experiments->titleFilter = " AND title LIKE '%$term%'";

        return $Experiments->read(false);
    }

    /**
     * Get a list of items with a filter on the $term
     *
     * @param string $term the query
     * @return array
     */
    public function getDbList(string $term): array
    {
        $Database = new Database($this->Users);
        $Database->titleFilter = " AND title LIKE '%$term%'";

        return $Database->read(false);
    }

    /**
     * Get an array formatted for the Link list on experiments
     *
     * @param string $term the query
     * @return array
     */
    public function getLinkList(string $term): array
    {
        $linksArr = array();
        $itemsArr = $this->getDbList($term);

        foreach ($itemsArr as $item) {
            $linksArr[] = $item['id'] . ' - ' . $item['category'] . ' - ' . substr($item['title'], 0, 60);
        }

        return $linksArr;
    }

    /**
     * Get an array of a mix of experiments and database items
     * for use with the mention plugin of tinymce (# and $ autocomplete)
     *
     * @param string $term the query
     * @return array
     */
    public function getMentionList(string $term): array
    {
        $mentionArr = array();

        // add items from database
        $itemsArr = $this->getDbList($term);
        foreach ($itemsArr as $item) {
            $mentionArr[] = array('name' => "<a href='database.php?mode=view&id=" .
                $item['id'] . "'>[" . $item['category'] . '] ' . $item['title'] . '</a>',
            );
        }

        // complete the list with experiments
        // fix #191
        $experimentsArr = $this->getExpList($term);
        foreach ($experimentsArr as $item) {
            $mentionArr[] = array('name' => "<a href='experiments.php?mode=view&id=" .
                $item['id'] . "'>[" . ngettext('Experiment', 'Experiments', 1) . '] ' . $item['title'] . '</a>',
            );
        }

        return $mentionArr;
    }

    /**
     * Update the category for an entity
     *
     * @param int $category id of the category (status or items types)
     * @return void
     */
    public function updateCategory(int $category): void
    {
        $this->canOrExplode('write');

        $sql = 'UPDATE ' . $this->type . ' SET category = :category WHERE id = :id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':category', $category, PDO::PARAM_INT);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Now that we have an id, load the data in entityData array
     *
     * @return void
     */
    protected function populate(): void
    {
        if ($this->id === null) {
            throw new ImproperActionException('No id was set.');
        }

        // load the entity in entityData array
        $this->entityData = $this->read();
    }
}
