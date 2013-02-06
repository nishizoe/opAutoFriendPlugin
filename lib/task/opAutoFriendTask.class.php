<?php
/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

class opAutoFriendTask extends sfBaseTask
{
  protected function configure()
  {
    $this->namespace        = 'opAutoFriend';
    $this->name             = 'auto';
    $this->briefDescription = 'This plugin makes friend link automatically.';
    $this->databaseManager = null;
    $this->detailedDescription = <<<EOF
  [./symfony opAutoFriend:auto --member=1]
EOF;

    $this->addOption('member', null, sfCommandOption::PARAMETER_OPTIONAL, 'member', null);
  }

  protected function execute($arguments = array(), $options = array())
  {
    $this->databaseManager = new sfDatabaseManager($this->configuration);

    if ($options['member'])
    {
      $this->autoFriendWithId($options['member']);
      echo "autoFriendWithMemberId\n";
    }
  }

  private function autoFriendWithId($memberId = null)
  {
    if (!$memberId)
    {
      return;
    }
    $conn = $this->databaseManager->getDatabase(array_shift($this->databaseManager->getNames()))->getConnection();
    //ターゲットメンバーの既存フレンドリンクを削除
    Doctrine_Query::create()
      ->delete()
      ->from('MemberRelationship')
      ->where('member_id_to = ?', $memberId)
      ->orWhere('member_id_from = ?', $memberId)
      ->execute();

    $sql = 'insert into member_relationship (';
    $sql .= 'member_id_to';
    $sql .= ', member_id_from';
    $sql .= ', is_friend';
    $sql .= ', is_friend_pre';
    $sql .= ') select';
    $sql .= ' id as member_id_to';
    $sql .= ', ? as member_id_from';
    $sql .= ', 1 as is_friend';
    $sql .= ', 0 as is_friend_pre';
    $sql .= ' from member';
    $sql .= ' where id <> ?';
    $sql .= ' and is_login_rejected = 0';
    $sql .= ' union select';
    $sql .= ' ? as member_id_to';
    $sql .= ' , id as member_id_from';
    $sql .= ' , 1 as is_friend';
    $sql .= ' , 0 as is_friend_pre';
    $sql .= ' from member';
    $sql .= ' where id <> ?';
    $sql .= ' and is_login_rejected = 0';

    $stmt = $conn->prepare($sql);
    $stmt->execute(array($memberId, $memberId, $memberId, $memberId));
  }
}
