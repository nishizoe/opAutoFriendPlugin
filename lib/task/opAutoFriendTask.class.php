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
  [./symfony opAutoFriend:auto]
EOF;

    $this->addOption('disconnectall', null,sfCommandOption::PARAMETER_NONE, 'disconnect', null);
    $this->addOption('disconnect', null,sfCommandOption::PARAMETER_NONE, 'disconnect', null);
    $this->addOption('member', null, sfCommandOption::PARAMETER_OPTIONAL, 'member', null);
    $this->addOption('community', null, sfCommandOption::PARAMETER_OPTIONAL, 'community', null);
  }

  protected function execute($arguments = array(), $options = array())
  {
    $this->databaseManager = new sfDatabaseManager($this->configuration);

    if ($options['disconnectall']){
      echo "try disconnect all\n";
      $this->disconnectall();
      echo "disconnect all done.\n";
      return;
    }

    if ($options['disconnect'])
    {
      if ($options['member'] && $options['community'])
      {
        $this->autoFriendDisconnectCommunity($options['member'], $options['community']);
      }
      else
      {
        die('parameter requied.');
      }
      echo "disconnect\n";

      return;
    }

    if ($options['member'])
    {
      $this->autoFriendWithId($options['member']);
      echo "autoFriendWithMemberId\n";
    }
    else if ($options['community'])
    {
      $this->autoFriendWithCommunityId($options['community']);
      echo "autoFriendWithCommunityId\n";
    }
    else
    {
      $this->autoFriendAll();
      echo "autoFriendAll\n";
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

  private function autoFriendAll()
  {
    Doctrine_Query::create()->delete('MemberRelationship s')->execute();
    $conn = $this->databaseManager->getDatabase(array_shift($this->databaseManager->getNames()))->getConnection();

    $sql = 'insert into member_relationship (';
    $sql .= ' member_id_to';
    $sql .= ', member_id_from';
    $sql .= ', is_friend';
    $sql .= ', is_friend_pre';
    $sql .= ') select';
    $sql .= ' m1.id as member_id_to';
    $sql .= ', m2.id as member_id_from';
    $sql .= ', 1 as is_friend';
    $sql .= ', 0 as is_friend_pre';
    $sql .= ' from member as m1';
    $sql .= ', member as m2';
    $sql .= ' where m1.id <> m2.id';
    $sql .= ' and m1.is_login_rejected = 0';
    $sql .= ' and m2.is_login_rejected = 0';

    $stmt = $conn->prepare($sql);
    $stmt->execute();
  }

  private function autoFriendWithCommunityId($communityId = null)
  {
    if (!$communityId)
    {
      return;
    }
    $idList = Doctrine_Query::create()
      ->select('cm.member_id')
      ->from('CommunityMember cm')
      ->where("cm.community_id = ?", $communityId)
      ->execute();

    foreach ($idList as $idFrom)
    {
      foreach ($idList as $idTo)
      {
        if ($idFrom['member_id'] != $idTo['member_id'])
        {
          $relationFromTo = Doctrine::getTable('MemberRelationship')
            ->retrieveByFromAndTo($idFrom['member_id'],$idTo['member_id']);
          $relationToFrom = Doctrine::getTable('MemberRelationship')
            ->retrieveByFromAndTo($idTo['member_id'],$idFrom['member_id']);
          if (!$relationFromTo && !$relationToFrom)
          {
            $this->saveMemberRelationship($idFrom['member_id'], $idTo['member_id'], 1);
            $this->saveMemberRelationship($idTo['member_id'], $idFrom['member_id'], 1);
          }
        }
      }
    }
  }

  private function autoFriendDisconnectCommunity($withoutMemberId, $communityId)
  {
    $idList = Doctrine_Query::create()
    ->select('cm.member_id')
    ->from('CommunityMember cm')
    ->where("cm.community_id = ?", $communityId)
    ->execute();

    foreach ($idList as $id){
      if($id['member_id'] == $withoutMemberId)
      {
        //skip
      }
      else
      {
        $this->disconnectWitoutId($id['member_id'], $withoutMemberId);
      }
    }
  }

  private function disconnectWitoutId($targetMemberId, $withoutMemberId)
  {
    //ターゲットメンバーの既存フレンドリンクを削除
    Doctrine_Query::create()
      ->delete()
      ->from('MemberRelationship')
      ->where('member_id_to = ?', $targetMemberId)
      ->orWhere('member_id_from = ?', $targetMemberId)
      ->execute();

    if ($targetMemberId == $withoutMemberId)
    {
      return;
    }
    //残すメンバーだけフレンドリンクしなおす
    $this->saveMemberRelationship($targetMemberId, $withoutMemberId, 1);
    $this->saveMemberRelationship($withoutMemberId, $targetMemberId, 1);
  }

  private function disconnectall()
  {
    Doctrine_Query::create()->delete('MemberRelationship')->execute();
  }

  /**
   * MemberRelationship save
   */
  private function saveMemberRelationship($from, $to, $isFriend)
  {
    $memberRelationship = new MemberRelationship();
    $memberRelationship->setMemberIdFrom($from);
    $memberRelationship->setMemberIdTo($to);
    $memberRelationship->setIsFriend($isFriend);
    $memberRelationship->save();
  }
}
