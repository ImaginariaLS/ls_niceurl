<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

class PluginNiceurl_ModuleNiceurl_MapperNiceurl extends Mapper
{


    public function GetTopicByTitleLat($sTitle)
    {
        $niceurl_table_topic = Config::Get('plugin.niceurl.table.topic');

        $sql = "SELECT id FROM {$niceurl_table_topic} WHERE title_lat = ? limit 0,1";
        if ($aRow = $this->oDb->selectRow($sql, $sTitle)) {
            return $aRow['id'];
        }
        return null;
    }

    public function UpdateTopic(PluginNiceurl_ModuleNiceurl_EntityTopic $oNiceurlTopic)
    {
        $niceurl_table_topic = Config::Get('plugin.niceurl.table.topic');

        $sql = "REPLACE INTO  {$niceurl_table_topic} 
			SET title_lat = ?, id = ?d ";
        if ($aRow = $this->oDb->query($sql, $oNiceurlTopic->getTitleLat(), $oNiceurlTopic->getId())) {
            return true;
        }
        return false;
    }

    public function DeleteTopicById($sId)
    {
        $niceurl_table_topic = Config::Get('plugin.niceurl.table.topic');

        $sql = "DELETE FROM {$niceurl_table_topic} WHERE id = ?d ";
        if ($aRow = $this->oDb->query($sql, $sId)) {
            return true;
        }
        return false;
    }

    public function GetTopicsByArrayId($aArrayId)
    {
        $niceurl_table_topic = Config::Get('plugin.niceurl.table.topic');

        if (!is_array($aArrayId) or count($aArrayId) == 0) {
            return array();
        }

        $sql = "SELECT 
					*							 
				FROM 
					{$niceurl_table_topic}
				WHERE 
					id IN(?a) 									
				ORDER BY FIELD(id,?a) ";
        $aTopics = array();
        if ($aRows = $this->oDb->select($sql, $aArrayId, $aArrayId)) {
            foreach ($aRows as $aTopic) {
                $aTopics[$aTopic['id']] = Engine::GetEntity('PluginNiceurl_Niceurl_Topic', $aTopic);
            }
        }
        return $aTopics;
    }


    public function GetTopicsHeadAll($iCurrPage, $iPerPage)
    {
        $niceurl_table_topic = Config::Get('plugin.niceurl.table.topic');
        $table_topic = Config::Get('db.table.topic');
        $table_topic_content = Config::Get('db.table.topic_content');


        $sql = "SELECT 
					t.*,
					tc.*							 
				FROM 
					{$table_topic} as t	
					JOIN {$table_topic_content} AS tc ON t.topic_id=tc.topic_id		
				LIMIT ?d, ?d ";
        $aTopics = array();
        if ($aRows = $this->oDb->select($sql, ($iCurrPage - 1) * $iPerPage, $iPerPage)) {
            foreach ($aRows as $aTopic) {
                $aTopics[] = Engine::GetEntity('Topic', $aTopic);
            }
        }
        return $aTopics;
    }
}
