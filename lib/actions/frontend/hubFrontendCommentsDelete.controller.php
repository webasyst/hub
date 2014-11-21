<?php

class hubFrontendCommentsDeleteController extends hubFrontendCommentsEditController
{
    protected function save()
    {
        $this->model->changeStatus($this->comment_id, hubCommentModel::STATUS_DELETED);
    }
}
