<?php
/**
 * Upvote or downvote a topic or comment. Used in both frontend and backend.
 */
class hubFrontendVoteController extends waJsonController
{
    public function execute()
    {
        $contact_id = wa()->getUser()->getId();
        if (!$contact_id) {
            return;
        }

        $vote = waRequest::request('vote', 0, 'int');
        $vote = ($vote >= 0) ? 1 : -1;

        $id = waRequest::request('id', 0, 'int');
        $type = waRequest::request('type');
        if ($type !== 'comment') {
            $type = 'topic';
        }

        if ($type == 'comment') {
            $comment_model = new hubCommentModel();
            $record = $comment_model->getById($id);
            $entity_type = empty($record['solution']) ? 'comment' : 'solution';
        } else {
            $topic_model = new hubTopicModel();
            $record = $topic_model->getById($id);
            $entity_type = 'topic';
        }

        if (empty($record) || empty($record['hub_id'])) {
            return;
        }

        // Access control: make sure the hub is either public, or user has access to it via backend
        if (!$this->checkRights($record['hub_id'])) {
            return;
        }

        $vote_model = new hubVoteModel();
        $votes_sum_change = $vote_model->vote($contact_id, $id, $type, $vote);

        if ($votes_sum_change && $record['contact_id'] != $contact_id) {
            $new_voter = abs($votes_sum_change) <= 1;
            $number_of_votes = $votes_sum_change / abs($votes_sum_change);
            $author_model = new hubAuthorModel();
            $author_model->receiveVote($entity_type, $record['hub_id'], $record['contact_id'], $number_of_votes, $new_voter);
        }

        // Renew stats after voting
        if ($type == 'comment') {
            $record = $comment_model->getById($id);
        } else {
            $record = $topic_model->getById($id);
        }

        if ($type == 'topic') {
            // For 'page' topic type user has an option to write comment when they vote negative
            if ($record && $vote < 0 && ( $comment_text = waRequest::post('comment', '', 'string'))) {
                $type_model = new hubTypeModel();
                $topic_type = $type_model->getById($record['type_id']);
                if ($topic_type && $topic_type['type'] == 'page') {

                    // Send notification email
                    try {
                        $c = new waContact($record['contact_id']);
                        $email = $c->get('email', 'default');
                        if ($email) {
                            $view = wa()->getView();
                            $view->assign(array(
                                'topic' => $record,
                                'sender' => wa()->getUser(),
                                'comment_text' => $comment_text,
                                'contact' => $c,
                            ));
                            $m = new waMailMessage();
                            $m->setSubject(_w('Someone suggested an improvement to your article'));
                            $m->setBody($view->fetch(wa()->getAppPath('templates/mail/Suggestion.html')));
                            $m->setTo($email, $c->getName());
                            $m->send();
                            // !!! write to wa_log?
                        }
                    } catch (Exception $e) {
                        waLog::log('Unable to send notification email to '.$email.': '.$e->getMessage());
                    }
                }
            }
        }

        if (!empty($record)) {
            $this->response = array_intersect_key($record, array(
                'votes_up' => 1, 'votes_down' => 1, 'votes_count' => 1, 'votes_sum' => 1,
            ));
        }
    }

    public function checkRights($hub_id)
    {
        if (empty($hub_id)) {
            return false;
        }

        $hubs = wa('hub')->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_READ);
        return !!$hubs[$hub_id];
    }
}
