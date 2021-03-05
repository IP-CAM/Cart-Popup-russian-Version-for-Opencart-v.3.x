<?php
class ModelExtensionModuleCartPopupNik extends Model {
    public function getExtensionByCode($code) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `code` = '" . $this->db->escape($code) . "'");

        return $query->row;
    }
}