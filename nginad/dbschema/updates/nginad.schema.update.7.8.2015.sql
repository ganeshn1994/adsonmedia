DROP TABLE IF EXISTS `LinkedBannerToAdZone`;
DROP TABLE IF EXISTS `LinkedBannerToAdZonePreview`;
DROP TABLE IF EXISTS `PublisherAdZoneType`;
DROP TABLE IF EXISTS `ContractPublisherZoneHourlyImpressions`;
-- ALTER TABLE PublisherAdZone DROP COLUMN `VisibilityTypeID`;
ALTER TABLE PublisherAdZone DROP COLUMN `PublisherAdZoneTypeID`;

-- ----------------------------
-- Table structure for PrivateExchangePublisherMarkup
-- ----------------------------
DROP TABLE IF EXISTS `PrivateExchangePublisherMarkup`;
CREATE TABLE `PrivateExchangePublisherMarkup` (
  `PublisherInfoID` int(11) NOT NULL,
  `MarkupRate` float NOT NULL,
  PRIMARY KEY (`PublisherInfoID`),
  UNIQUE KEY `PublisherInfoID` (`PublisherInfoID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for PrivateExchangePublisherWebsiteMarkup
-- ----------------------------
DROP TABLE IF EXISTS `PrivateExchangePublisherWebsiteMarkup`;
CREATE TABLE `PrivateExchangePublisherWebsiteMarkup` (
  `PublisherWebsiteID` int(11) NOT NULL,
  `MarkupRate` float NOT NULL,
  PRIMARY KEY (`PublisherWebsiteID`),
  UNIQUE KEY `PublisherWebsiteID` (`PublisherWebsiteID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE  `SellSidePartnerHourlyBids` ADD `SpendTotalPrivateExchangeGross` float NOT NULL AFTER `SpendTotalGross` ;
ALTER TABLE  `PublisherHourlyBids` ADD `SpendTotalPrivateExchangeGross` float NOT NULL AFTER `SpendTotalGross` ;

-- ----------------------------
-- View structure for PublisherImpressionsAndSpendHourly
-- ----------------------------
DROP VIEW IF EXISTS `PublisherImpressionsAndSpendHourly`;
CREATE VIEW `PublisherImpressionsAndSpendHourly` AS select `phb`.`MDYH` AS `MDYH`,`phb`.`PublisherAdZoneID` AS `PublisherAdZoneID`,`pi`.`Name` AS `PublisherName`,`pad`.`AdOwnerID` AS `PublisherInfoID`,`pad`.`AdName` AS `AdName`,`phb`.`AuctionCounter` AS `Requests`,`phb`.`BidsWonCounter` AS `Impressions`,round(((`phb`.`SpendTotalNet` / `phb`.`BidsWonCounter`) * 1000),7) AS `eCPM`,round(((`phb`.`SpendTotalPrivateExchangeGross` / `phb`.`BidsWonCounter`) * 1000),7) AS `GrossECPM`,round(((`phb`.`SpendTotalGross` / `phb`.`BidsWonCounter`) * 1000),7) AS `GrossExchangeECPM`,concat(round(((`phb`.`BidsWonCounter` / `phb`.`AuctionCounter`) * 100),2),'%') AS `FillRate`,round(`phb`.`SpendTotalNet`,7) AS `Revenue`,round(`phb`.`SpendTotalPrivateExchangeGross`,7) AS `GrossRevenue`,round(`phb`.`SpendTotalGross`,7) AS `GrossExchangeRevenue`,`phb`.`DateCreated` AS `DateCreated` from ((`PublisherHourlyBids` `phb` join `PublisherAdZone` `pad` on((`phb`.`PublisherAdZoneID` = `pad`.`PublisherAdZoneID`))) join `PublisherInfo` `pi` on((`pad`.`AdOwnerID` = `pi`.`PublisherInfoID`))) ;

-- Add Credit app data and bits for PlatformConnection and SSP Inventory approvals

ALTER TABLE  `DemandCustomerInfo` ADD `ApprovedForPlatformConnectionInventory` tinyint(4) NOT NULL DEFAULT '0' AFTER `PartnerType` ;
ALTER TABLE  `DemandCustomerInfo` ADD `ApprovedForSspRtbInventory` tinyint(4) NOT NULL DEFAULT '0' AFTER `ApprovedForPlatformConnectionInventory` ;
ALTER TABLE  `DemandCustomerInfo` ADD `CreditApplicationWasSent` tinyint(4) NOT NULL DEFAULT '0' AFTER `ApprovedForSspRtbInventory` ;
ALTER TABLE  `DemandCustomerInfo` ADD `DateCreditApplicationWasSent` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `CreditApplicationWasSent` ;

-- Remove columns we can't support in the near future with OpenRTB
-- Most of these are from Pubmatic
-- Keep Frequency and other columns not dependent on OpenRTB data exclusively

ALTER TABLE InsertionOrderLineItemRestrictions DROP COLUMN `InMultipleNestedIframes`;
ALTER TABLE InsertionOrderLineItemRestrictions DROP COLUMN `CookieGrep`;
ALTER TABLE InsertionOrderLineItemRestrictions DROP COLUMN `PmpEnable`;

ALTER TABLE InsertionOrderLineItemVideoRestrictions DROP COLUMN `PmpEnable`;

ALTER TABLE InsertionOrderLineItemRestrictionsPreview DROP COLUMN `InMultipleNestedIframes`;
ALTER TABLE InsertionOrderLineItemRestrictionsPreview DROP COLUMN `CookieGrep`;
ALTER TABLE InsertionOrderLineItemRestrictionsPreview DROP COLUMN `PmpEnable`;

ALTER TABLE InsertionOrderLineItemVideoRestrictionsPreview DROP COLUMN `PmpEnable`;

-- Add SQL Tables to hold the Vanity Domain Information

-- ----------------------------
-- Table structure for PrivateExchangeTheme
-- ----------------------------
DROP TABLE IF EXISTS `PrivateExchangeTheme`;
CREATE TABLE `PrivateExchangeTheme` (
  `UserID` int(11) NOT NULL,
  `ThemeParamsSerialized` text NOT NULL,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `UserIDKey` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for PrivateExchangeVanityDomain
-- ----------------------------
DROP TABLE IF EXISTS `PrivateExchangeVanityDomain`;
CREATE TABLE `PrivateExchangeVanityDomain` (
  `UserID` int(11) NOT NULL,
  `VanityDomain` char(255) NOT NULL,
  `UseLogo` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `UserIDKey` (`UserID`),
  UNIQUE KEY `VanityDomainKey` (`VanityDomain`),
  INDEX `VanityDomainIdx` (`VanityDomain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



