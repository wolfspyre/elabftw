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

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Elabftw\Elabftw\Db;
use Elabftw\Elabftw\Sql;
use Elabftw\Elabftw\Update;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Services\Check;
use PDO;

/**
 * The general config table
 */
class Config
{
    /** @var array $configArr the array with all config */
    public $configArr;

    /** @var Db $Db SQL Database */
    protected $Db;

    /**
     * Get Db and load the configArr
     *
     */
    public function __construct()
    {
        $this->Db = Db::getConnection();
        $this->configArr = $this->read();
        // this should only run once: just after a fresh install
        if (empty($this->configArr)) {
            $this->populate();
            $this->configArr = $this->read();
        }
    }

    /**
     * Read the configuration values
     *
     * @return array
     */
    public function read(): array
    {
        $configArr = array();

        $sql = 'SELECT * FROM config';
        $req = $this->Db->prepare($sql);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        $config = $req->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
        if ($config === false) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        foreach ($config as $name => $value) {
            $configArr[$name] = $value[0];
        }
        return $configArr;
    }

    /**
     * Used in sysconfig.php to update config values
     *
     * @param array $post (conf_name => conf_value)
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @return void
     */
    public function update(array $post): void
    {
        // do some data validation for some values
        /* TODO add upload button
        if (isset($post['stampcert'])) {
            $cert_chain = filter_var($post['stampcert'], FILTER_SANITIZE_STRING);
            if (!is_readable(realpath($cert_chain))) {
                throw new Exception('Cannot read provided certificate file.');
            }
        }
         */

        if (isset($post['stamppass']) && !empty($post['stamppass'])) {
            $post['stamppass'] = Crypto::encrypt($post['stamppass'], Key::loadFromAsciiSafeString(\SECRET_KEY));
        } elseif (isset($post['stamppass'])) {
            unset($post['stamppass']);
        }

        // sanitize canonical URL
        if (isset($post['url']) && !empty($post['url'])) {
            $post['url'] = filter_var($post['url'], FILTER_SANITIZE_URL);
        }

        if (isset($post['login_tries']) && Check::id((int) $post['login_tries']) === false) {
            throw new IllegalActionException('Bad value for number of login attempts!');
        }
        if (isset($post['ban_time']) && Check::id((int) $post['ban_time']) === false) {
            throw new IllegalActionException('Bad value for number of login attempts!');
        }

        // encrypt password
        if (isset($post['smtp_password']) && !empty($post['smtp_password'])) {
            $post['smtp_password'] = Crypto::encrypt($post['smtp_password'], Key::loadFromAsciiSafeString(\SECRET_KEY));
        } elseif (isset($post['smtp_password'])) {
            unset($post['smtp_password']);
        }

        // loop the array and update config
        foreach ($post as $name => $value) {
            $sql = 'UPDATE config SET conf_value = :value WHERE conf_name = :name';
            $req = $this->Db->prepare($sql);
            $req->bindParam(':value', $value);
            $req->bindParam(':name', $name);
            if ($req->execute() !== true) {
                throw new DatabaseErrorException('Error while executing SQL query.');
            }
        }
    }

    /**
     * Reset the timestamp password
     *
     * @return void
     */
    public function destroyStamppass(): void
    {
        $sql = "UPDATE config SET conf_value = NULL WHERE conf_name = 'stamppass'";
        $req = $this->Db->prepare($sql);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Restore default values
     *
     * @return void
     */
    public function restoreDefaults(): void
    {
        $sql = 'DELETE FROM config';
        $req = $this->Db->prepare($sql);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        $this->populate();
    }

    /**
     * Insert the default values in config
     *
     * @return void
     */
    private function populate(): void
    {
        $Update = new Update($this, new Sql());
        $schema = $Update->getRequiredSchema();

        $sql = "INSERT INTO `config` (`conf_name`, `conf_value`) VALUES
            ('admin_validate', '1'),
            ('ban_time', '60'),
            ('debug', '0'),
            ('lang', 'en_GB'),
            ('login_tries', '3'),
            ('mail_from', 'notconfigured@example.com'),
            ('mail_method', 'smtp'),
            ('proxy', ''),
            ('sendmail_path', '/usr/sbin/sendmail'),
            ('smtp_address', 'mail.smtp2go.com'),
            ('smtp_encryption', 'tls'),
            ('smtp_password', ''),
            ('smtp_port', '2525'),
            ('smtp_username', ''),
            ('stamplogin', ''),
            ('stamppass', ''),
            ('stampshare', '1'),
            ('stampprovider', 'http://zeitstempel.dfn.de/'),
            ('stampcert', 'src/dfn-cert/pki.dfn.pem'),
            ('stamphash', 'sha256'),
            ('saml_toggle', '0'),
            ('saml_debug', '0'),
            ('saml_strict', '1'),
            ('saml_baseurl', NULL),
            ('saml_entityid', NULL),
            ('saml_acs_url', NULL),
            ('saml_acs_binding', NULL),
            ('saml_slo_url', NULL),
            ('saml_slo_binding', NULL),
            ('saml_nameidformat', NULL),
            ('saml_x509', NULL),
            ('saml_privatekey', NULL),
            ('saml_team', NULL),
            ('saml_team_create', '1'),
            ('saml_team_default', NULL),
            ('saml_email', NULL),
            ('saml_firstname', NULL),
            ('saml_lastname', NULL),
            ('local_login', '1'),
            ('local_register', '1'),
            ('anon_users', '0'),
            ('url', NULL),
            ('schema', :schema),
            ('open_science', '0'),
            ('open_team', NULL),
            ('privacy_policy', NULL),
            ('announcement', NULL),
            ('saml_nameidencrypted', 0),
            ('saml_authnrequestssigned', 0),
            ('saml_logoutrequestsigned', 0),
            ('saml_logoutresponsesigned', 0),
            ('saml_signmetadata', 0),
            ('saml_wantmessagessigned', 0),
            ('saml_wantassertionsencrypted', 0),
            ('saml_wantassertionssigned', 0),
            ('saml_wantnameid', 1),
            ('saml_wantnameidencrypted', 0),
            ('saml_wantxmlvalidation', 1),
            ('saml_relaxdestinationvalidation', 0),
            ('saml_lowercaseurlencoding', 0),
            ('deletable_xp', 1);";

        $req = $this->Db->prepare($sql);
        $req->bindParam(':schema', $schema);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }
}
