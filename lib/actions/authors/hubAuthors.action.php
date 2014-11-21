<?php
/**
 * List of all authors in backend.
 */
class hubAuthorsAction extends waViewAction
{
    public function execute()
    {
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);

        $author_model = new hubAuthorModel();
        $authors = $author_model->getList('*,stats_by_hub', array(
            'offset' => $offset,
        ), $total_count);

        // Are kudos turned on in any hub?
        $kudos_globally_enabled = false;
        $hubs = wa('hub')->getConfig()->getAvailableHubs();
        foreach($hubs as $h) {
            if (!empty($h['params']['kudos'])) {
                $kudos_globally_enabled = true;
                break;
            }
        }

        $this->view->assign(array(
            'offset' => $offset,
            'count' => count($authors),
            'total_count' => $total_count,
            'kudos_globally_enabled' => $kudos_globally_enabled,
            'authors' => $authors,
            'hubs' => $hubs,
        ));
    }
}
