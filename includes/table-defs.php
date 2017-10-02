<?php
$this->tableDefinitions = array(
    "notification"=>" (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `email` varchar(191) NOT NULL,
      `notification_type` varchar(45) NOT NULL,
      `notification_date` datetime NOT NULL,
      `feedback_id` varchar(191) NOT NULL,
      `bounce_type` varchar(45) DEFAULT NULL,
      `bounce_subtype` varchar(45) DEFAULT NULL,
      `complaint_feedback_type` varchar(45) DEFAULT NULL,
      `resend` tinyint(4) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `gnses_email` (`email`),
      KEY `gnses_ntype` (`notification_type`),
      KEY `gnses_ndate` (`notification_date`),
      KEY `gnses_btype` (`bounce_type`,`bounce_subtype`),
      KEY `gnses_cfb` (`complaint_feedback_type`)
    )"
);
