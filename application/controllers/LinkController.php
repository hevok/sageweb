<?php

/**
 * @author brady
 */
class LinkController extends Zend_Controller_Action
{
    const ENTITY_TYPE = Sageweb_Entity::TYPE_LINK;

    public function showAction()
    {
        $id = $this->_getParam('id');
        $post = Sageweb_Table_Link::findOneById($id);
        if (!$post) {
            throw new Zend_Controller_Action_Exception('Page not found', 404);
        }

        if (!empty($post->slug) && preg_match('/^\d+$/', $id)) {
            $fullUri = '/links/' . $post->getSlugId();
            $this->_redirect($fullUri, array('code' => 301));
        }

        // increment view counter
        $viewingUser = Application_Registry::getCurrentUser();
        if ($post->isPublic()) {
            $post->incrementViews($viewingUser);

            // fetch vote record
            $existingVote = $viewingUser->getVote($post->entity);
            if ($existingVote) {
                $this->view->voteValue = $existingVote->value;
            }
        }

        $this->view->canEdit = $viewingUser->canEdit($post);
        $this->view->post = $post;
    }

    public function newAction()
    {
        $viewingUser = Application_Registry::getCurrentUser();
        $form = new Application_Form_PostLink(array('viewingUser' => $viewingUser));
        if ($this->getRequest()->isPost()) {
            if($form->isValid($_POST)) {
                $formValues = $form->getValues();
                $formValues['tags'] = Application_Converter_Tags::getArray($formValues['tags']);

                // create a new article entry
                $data = $this->_getRevisionData($formValues, $viewingUser);
                $post = Sageweb_Table_Entity::createPost(self::ENTITY_TYPE, $data);

                // create revision entry (pendign => public)
                $data['status'] = Sageweb_Abstract_Post::STATUS_PUBLIC;
                $revision = $viewingUser->createRevision($post->entity, $data);
                if ($viewingUser->isModerator()) {
                    $comment = $formValues['reviewerComment'];
                    $viewingUser->acceptRevision($revision, $comment);
                }

                $url = $this->view->url(array('id' => $post->getSlugId()), 'link', true);
                $this->_redirect($url);
            }
        } else {
            if ($viewingUser->isModerator()) {
                $form->getElement('author')->setValue($viewingUser->username);
            }
        }
        $this->view->form = $form;
    }

    public function editAction()
    {
        $id = $this->getRequest()->getParam('id');
        $post = Sageweb_Table_Link::findOneById($id);
        if (!$post) {
            throw new Zend_Controller_Action_Exception('Page not found', 404);
        }

        $viewingUser = Application_Registry::getCurrentUser();
        if (!$viewingUser->canEdit($post)) {
            throw new Zend_Controller_Action_Exception('Permission denied.', 404);
        }
        
        $form = new Application_Form_PostLink(array('viewingUser' =>  $viewingUser));
        if ($this->getRequest()->isPost()) {
            if ($form->isValid($this->_getAllParams())) {
                $formValues = $form->getValues();
                $formValues['tags'] = Application_Converter_Tags::getArray($formValues['tags']);

                // save revision entry
                $data = $this->_getRevisionData($formValues, $viewingUser);
                $data['updatedAt'] = date('Y-m-d H:i:s');
                $comment = $formValues['creatorComment'];
                $revision = $viewingUser->createRevision($post->entity, $data, $comment);
                if ($viewingUser->isModerator()) {
                    $comment = $formValues['reviewerComment'];
                    $viewingUser->acceptRevision($revision, $comment);
                }

                $url = $this->view->url(array('id' => $post->getSlugId()), 'link', true);
                $this->_redirect($url);
            }
        } else {
            $form->populate($post->toArray());
        }

        $this->view->post = $post;
        $this->view->form = $form;
    }

    private function _getRevisionData($formValues, $viewingUser)
    {
        $revisionData['authorId'] = $viewingUser->id;
        $revisionData['title'] = $formValues['title'];
        $revisionData['summary'] = $formValues['summary'];
        $revisionData['url'] = $formValues['url'];
        $revisionData['categories'] = $formValues['categories'];
        $revisionData['tags'] = $formValues['tags'];
        $revisionData['isFeatured'] = false;
        if ($viewingUser->isModerator()) {
            $revisionData['status'] = $formValues['status'];
            $revisionData['isFeatured'] = $formValues['isFeatured'];

            $username = $formValues['author'];
            $author = Sageweb_Table_User::findOneByUsername($username);
            $revisionData['authorId'] = $author->id;
        }
        return $revisionData;
    }

    public function revisionsAction()
    {
        $id = $this->_getParam('id');
        $post = Sageweb_Table_Link::findOneById($id);
        if (!$post) {
            throw new Zend_Controller_Action_Exception(404, 'Post not found.');
        }
        
        $revisions = Sageweb_Table_Revision::findByEntityId($post->entityId);

        $this->view->post = $post;
        $this->view->revisions = $revisions;
    }

    public function revisionAction()
    {
        $id = $this->_getParam('id');
        $post = Sageweb_Table_Link::findOneById($id);
        if (!$post) {
            throw new Zend_Controller_Action_Exception(404, 'Post not found.');
        }

        $revisionId = $this->_getParam('revisionId');
        $revision = Sageweb_Table_Revision::findOneByEntityId($post->entityId, $revisionId);
        if (!$revision) {
            throw new Zend_Controller_Action_Exception(404, 'Revision not found.');
        }

        if ($this->_request->isPost()) {
            $viewingUser = Application_Registry::getCurrentUser();
            if ($viewingUser->isModerator()) {
                // accept or reject revision
                $comment = $this->_getParam('reviewerComment');
                $status = $this->_getParam('status');
                if ($status == Sageweb_EntityRevision::STATUS_ACCEPTED) {
                    $viewingUser->acceptRevision($revision, $comment);
                } else {
                    $viewingUser->rejectRevision($revision, $comment);
                }

                $url = $this->view->url(
                    array('action' => 'revisions', 'id' => $post->id), 'link', true);
                $this->_redirect($url);
            }
        }

        $this->view->post = $post;
        $this->view->currentData = $post->getRevisionData();

        $this->view->revision = $revision;
        $this->view->revisionData = Zend_Json::decode($revision->jsonData);
    }
}
