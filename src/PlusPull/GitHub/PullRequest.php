<?php

namespace PlusPull\GitHub;

class PullRequest
{
    public $number;

    public $title;

    public $comments = array();

    public $statuses = array();

    public $user;

    /**
     * @var boolean
     */
    public $isMergeable = false;

    public function checkComments($required = 3, $whitelist = null)
    {
        $voted = array();
        $total = 0;
        foreach ($this->comments as $comment) {
            $login = $comment->login;
            if ($whitelist && !in_array($login, $whitelist)) {
                continue;
            }

            if ($this->isBlocker($comment->body)) {
                return false;
            }

            if ($login == $this->user) {
                continue;
            }

            if (empty($voted[$login])) {
                $commentValue = $this->getCommentValue($comment->body);
                $total += $commentValue;
                $voted[$comment->login] = $commentValue!=0;
            }
        }
        return $total >= $required;
    }

    public function isBlocker($commentBody)
    {
        return preg_match('/^\[B\]/', $commentBody);
    }

    public function getCommentValue($commentBody)
    {
        if (preg_match('/^(\+1\b|:\+1:)/', $commentBody)) {
            return 1;
        }
        if (preg_match('/^(\-1\b|:\-1:)/', $commentBody)) {
            return -1;
        }
        return 0;
    }

    public function checkStatuses()
    {
        if (empty($this->statuses[0]['state'])) {
            return false;
        }

        return $this->statuses[0]['state']=='success';
    }

    public function isMergeable()
    {
        return $this->isMergeable;
    }
}
