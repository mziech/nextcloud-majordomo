<?php

namespace OCA\Majordomo\Service;

use OCA\Majordomo\Db\MailingList;
use OCA\Majordomo\Db\Member;

class MailingListAccess {

    const VIEW_ACCESS = "view_access";
    const MEMBER_EDIT_ACCESS = "member_edit_access";

    private int $access;  // for __toString
    public bool $canView;
    public bool $canResend;
    public bool $canListMembers;
    public bool $canEditMembers;
    public bool $canAdmin;
    public array $editableTypes;

    public function __construct(MailingList $ml, int $access) {
        $this->access = $access;
        $this->canView = $ml->viewAccess >= $access;
        $this->canResend = $ml->resendAccess >= $access;
        $this->canListMembers = $ml->memberListAccess >= $access;
        $this->canEditMembers = $ml->memberEditAccess >= $access;
        $this->canAdmin = MailingList::ACCESS_ADMIN >= $access;

        if (!$this->canEditMembers) {
            $this->editableTypes = [];
        } else if ($this->access <= MailingList::ACCESS_ADMIN) {
            $this->editableTypes = array_merge(Member::TYPES_RECIPIENT, Member::TYPES_MODERATOR, Member::TYPES_ADMIN);
        } else if ($this->access <= MailingList::ACCESS_MODERATORS) {
            $this->editableTypes = array_merge(Member::TYPES_RECIPIENT, Member::TYPES_MODERATOR);
        } else {
            $this->editableTypes = Member::TYPES_RECIPIENT;
        }
    }

    public function __toString(): string {
        return print_r($this, true);
    }

}
