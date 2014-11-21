<?php
class hubTopicsChangeBadgeController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::get('id');
        $badge = waRequest::post('badge');
        $badge = ifempty($badge, null);

        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($id);
        if (!$topic) {
            throw new waException('Topic not found', 404);
        }

        // Does user have access to topic's hub?
        $access_level = wa()->getUser()->getRights('hub', 'hub.'.$topic['hub_id']);
        if (
            ($access_level < hubRightConfig::RIGHT_READ_WRITE)
            ||
            (($access_level == hubRightConfig::RIGHT_READ_WRITE) && ($topic['contact_id'] != wa()->getUser()->getId()))
        ) {
            throw new waException('Access denied', 403);
        }

        // Does this topic's type allow this badge?
        $badges = hubHelper::getBadgesByType($topic['type_id']);
        if ($badge && empty($badges[$badge])) {
            return;
        }

        $update = array(
            'badge' => $badge,
        );

        // Archived topics have low priority
        $old_badge = $topic['badge'];
        if ($badge == 'archived') {
            $update['priority'] = -1;
        } else if ($old_badge == 'archived' && $topic['priority'] < 0) {
            $update['priority'] = 0;
        }

        $topic_model->updateById($id, $update);

    }
}

