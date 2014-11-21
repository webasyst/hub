<?php

class hubCommentsChangeSolutionController extends waJsonController
{
    public function execute()
    {
        $comment_id = waRequest::post('comment_id', null, waRequest::TYPE_INT);
        $model = new hubCommentModel();
        $comment = $model->getComment($comment_id);
        if (!$comment) {
            throw new waException(_w("Unknown comment"));
        }
        
        if (!$model->changeSolution($comment_id, waRequest::post("solution"))) {
            $this->errors[] = _w("Error occurs");
        }
    }
}
