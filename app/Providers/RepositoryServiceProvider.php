<?php

namespace App\Providers;

#region includes

use App\Repository\IRepository;
use App\Repository\BaseRepository;
use App\Repository\CRM\CRMRepository;

// use App\Repository\BaseRepository;
// use App\Repository\IRepository;

use App\Repository\CRM\ICRMRepository;
use App\Repository\Logs\LogsRepository;

use Illuminate\Support\ServiceProvider;
use App\Repository\Forms\FormRepository;

use App\Repository\Logs\ILogsRepository;
use App\Repository\Users\UserRepository;

use App\Repository\Forms\IFormRepository;
use App\Repository\Leads\LeadsRepository;

use App\Repository\Users\IUserRepository;
use App\Repository\Leads\ILeadsRepository;

use App\Repository\Report\ReportRepository;
use App\Repository\Brokers\BrokerRepository;

use App\Repository\Clients\ClientRepository;
use App\Repository\Masters\MasterRepository;

use App\Repository\Report\IReportRepository;
use App\Repository\Brokers\IBrokerRepository;

use App\Repository\Clients\IClientRepository;
use App\Repository\FireFTD\FireFTDRepository;

use App\Repository\Gravity\GravityRepository;
use App\Repository\Masters\IMasterRepository;

use App\Repository\Storage\StorageRepository;
use App\Repository\Support\SupportRepository;
use App\Repository\FireFTD\IFireFTDRepository;
use App\Repository\Gravity\IGravityRepository;
use App\Repository\Storage\IStorageRepository;
use App\Repository\Support\ISupportRepository;
use App\Repository\Billings\BillingsRepository;

use App\Repository\Planning\PlanningRepository;
use App\Repository\Settings\SettingsRepository;
use App\Repository\Billings\IBillingsRepository;
use App\Repository\Brokers\BrokerCapsRepository;
use App\Repository\Planning\IPlanningRepository;
use App\Repository\Settings\ISettingsRepository;

use App\Repository\Brokers\IBrokerCapsRepository;
use App\Repository\Campaigns\CampaignsRepository;

use App\Repository\Dashboard\DashboardRepository;
use App\Repository\Affiliates\AffiliateRepository;

use App\Repository\Campaigns\ICampaignsRepository;
use App\Repository\Dashboard\IDashboardRepository;

use App\Repository\Affiliates\IAffiliateRepository;
use App\Repository\Brokers\BrokerBillingRepository;

use App\Repository\Brokers\BrokerPayoutsRepository;
use App\Repository\Brokers\BrokerSpyToolRepository;
use App\Repository\Masters\MasterBillingRepository;
use App\Repository\Masters\MasterPayoutsRepository;
use App\Repository\Brokers\IBrokerBillingRepository;
use App\Repository\Brokers\IBrokerPayoutsRepository;
use App\Repository\Brokers\IBrokerSpyToolRepository;
use App\Repository\Masters\IMasterBillingRepository;
use App\Repository\Masters\IMasterPayoutsRepository;
use App\Repository\Advertisers\AdvertisersRepository;
use App\Repository\ClickReport\ClickReportRepository;
use App\Repository\Dictionaries\DictionaryRepository;
use App\Repository\Investigate\InvestigateRepository;
use App\Repository\LeadsReview\LeadsReviewRepository;
use App\Repository\Performance\PerformanceRepository;
use App\Repository\Advertisers\IAdvertisersRepository;
use App\Repository\Campaigns\CampaignBudgetRepository;
use App\Repository\ClickReport\IClickReportRepository;
use App\Repository\Dictionaries\IDictionaryRepository;
use App\Repository\Investigate\IInvestigateRepository;
use App\Repository\LeadsReview\ILeadsReviewRepository;
use App\Repository\Performance\IPerformanceRepository;
use App\Repository\Brokers\BrokerIntegrationRepository;
use App\Repository\Campaigns\CampaignPayoutsRepository;
use App\Repository\Campaigns\ICampaignBudgetRepository;
use App\Repository\Brokers\BrokerPrivateDealsRepository;
use App\Repository\Brokers\IBrokerIntegrationRepository;
use App\Repository\Campaigns\ICampaignPayoutsRepository;
use App\Repository\Affiliates\AffiliateBillingRepository;
use App\Repository\Brokers\IBrokerPrivateDealsRepository;
use App\Repository\EmailsChecker\EmailsCheckerRepository;
use App\Repository\Notifications\NotificationsRepository;
use App\Repository\QualityReport\QualityReportRepository;
use App\Repository\TagManagement\TagManagementRepository;
use App\Repository\Affiliates\IAffiliateBillingRepository;
use App\Repository\EmailsChecker\IEmailsCheckerRepository;
use App\Repository\Notifications\INotificationsRepository;
use App\Repository\QualityReport\IQualityReportRepository;
use App\Repository\TagManagement\ITagManagementRepository;
use App\Repository\Brokers\BrokerStatusManagmentRepository;
use App\Repository\Campaigns\CampaignsLimitationRepository;
use App\Repository\MarketingSuite\MarketingSuiteRepository;
use App\Repository\Advertisers\AdvertisersBillingRepository;
use App\Repository\Brokers\IBrokerStatusManagmentRepository;
use App\Repository\Campaigns\CampaignPrivateDealsRepository;
use App\Repository\Campaigns\ICampaignsLimitationRepository;
use App\Repository\MarketingSuite\IMarketingSuiteRepository;
use App\Repository\Advertisers\IAdvertisersBillingRepository;
use App\Repository\Campaigns\ICampaignPrivateDealsRepository;
use App\Repository\MarketingReport\MarketingReportRepository;
use App\Repository\Advertisers\AdvertiserPostEventsRepository;
use App\Repository\MarketingReport\IMarketingReportRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointRepository;
use App\Repository\Advertisers\IAdvertiserPostEventsRepository;
use App\Repository\MarketingGravity\MarketingGravityRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointRepository;
use App\Repository\MarketingGravity\IMarketingGravityRepository;
use App\Repository\MarketingBillings\MarketingBillingsRepository;
use App\Repository\Campaigns\CampaignTargetingLocationsRepository;
use App\Repository\MarketingBillings\IMarketingBillingsRepository;
use App\Repository\Campaigns\ICampaignTargetingLocationsRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointScrubRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointScrubRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointBillingRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointPayoutsRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointBillingRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointPayoutsRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointSecurityRepository;
use App\Repository\MarketingInvestigate\MarketingInvestigateRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointSecurityRepository;
use App\Repository\MarketingInvestigate\IMarketingInvestigateRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointPrivateDealsRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointPrivateDealsRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointSubPublisherTokensRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointSubPublisherTokensRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointDynamicIntegrationIDsRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointDynamicIntegrationIDsRepository;

#endregion

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // $this->app->bind(IRepository::class, BaseRepository::class);
        $this->app->bind(IUserRepository::class, UserRepository::class);
        $this->app->bind(IFormRepository::class, FormRepository::class);

        $this->app->bind(IClientRepository::class, ClientRepository::class);

        $this->app->bind(IBrokerRepository::class, BrokerRepository::class);
        $this->app->bind(IBrokerCapsRepository::class, BrokerCapsRepository::class);
        $this->app->bind(IBrokerIntegrationRepository::class, BrokerIntegrationRepository::class);
        $this->app->bind(IBrokerStatusManagmentRepository::class, BrokerStatusManagmentRepository::class);
        $this->app->bind(IBrokerPayoutsRepository::class, BrokerPayoutsRepository::class);
        $this->app->bind(IBrokerPrivateDealsRepository::class, BrokerPrivateDealsRepository::class);
        $this->app->bind(IBrokerBillingRepository::class, BrokerBillingRepository::class);
        $this->app->bind(IBrokerSpyToolRepository::class, BrokerSpyToolRepository::class);

        $this->app->bind(IMasterRepository::class, MasterRepository::class);
        $this->app->bind(IMasterPayoutsRepository::class, MasterPayoutsRepository::class);
        $this->app->bind(IMasterBillingRepository::class, MasterBillingRepository::class);

        $this->app->bind(ITrafficEndpointRepository::class, TrafficEndpointRepository::class);
        $this->app->bind(ITrafficEndpointPayoutsRepository::class, TrafficEndpointPayoutsRepository::class);
        $this->app->bind(ITrafficEndpointSecurityRepository::class, TrafficEndpointSecurityRepository::class);
        $this->app->bind(ITrafficEndpointScrubRepository::class, TrafficEndpointScrubRepository::class);
        $this->app->bind(ITrafficEndpointBillingRepository::class, TrafficEndpointBillingRepository::class);
        $this->app->bind(ITrafficEndpointPrivateDealsRepository::class, TrafficEndpointPrivateDealsRepository::class);
        $this->app->bind(ITrafficEndpointDynamicIntegrationIDsRepository::class, TrafficEndpointDynamicIntegrationIDsRepository::class);

        $this->app->bind(IMarketingSuiteRepository::class, MarketingSuiteRepository::class);

        $this->app->bind(IDictionaryRepository::class, DictionaryRepository::class);

        $this->app->bind(IPlanningRepository::class, PlanningRepository::class);

        $this->app->bind(ISupportRepository::class, SupportRepository::class);

        $this->app->bind(IFireFTDRepository::class, FireFTDRepository::class);

        $this->app->bind(IEmailsCheckerRepository::class, EmailsCheckerRepository::class);

        $this->app->bind(IGravityRepository::class, GravityRepository::class);

        $this->app->bind(ICRMRepository::class, CRMRepository::class);

        $this->app->bind(IQualityReportRepository::class, QualityReportRepository::class);

        $this->app->bind(IReportRepository::class, ReportRepository::class);

        $this->app->bind(IClickReportRepository::class, ClickReportRepository::class);

        $this->app->bind(IBillingsRepository::class, BillingsRepository::class);

        $this->app->bind(IStorageRepository::class, StorageRepository::class);

        $this->app->bind(IDashboardRepository::class, DashboardRepository::class);

        $this->app->bind(IPerformanceRepository::class, PerformanceRepository::class);

        $this->app->bind(IInvestigateRepository::class, InvestigateRepository::class);

        $this->app->bind(ILeadsRepository::class, LeadsRepository::class);

        $this->app->bind(ISettingsRepository::class, SettingsRepository::class);

        $this->app->bind(ILogsRepository::class, LogsRepository::class);

        $this->app->bind(INotificationsRepository::class, NotificationsRepository::class);

        $this->app->bind(ILeadsReviewRepository::class, LeadsReviewRepository::class);

        $this->app->bind(ICampaignsRepository::class, CampaignsRepository::class);

        $this->app->bind(ICampaignPayoutsRepository::class, CampaignPayoutsRepository::class);

        $this->app->bind(ICampaignBudgetRepository::class, CampaignBudgetRepository::class);

        $this->app->bind(ICampaignTargetingLocationsRepository::class, CampaignTargetingLocationsRepository::class);

        $this->app->bind(ICampaignsLimitationRepository::class, CampaignsLimitationRepository::class);

        $this->app->bind(IAdvertisersRepository::class, AdvertisersRepository::class);

        $this->app->bind(IAdvertiserPostEventsRepository::class, AdvertiserPostEventsRepository::class);

        $this->app->bind(IAdvertisersBillingRepository::class, AdvertisersBillingRepository::class);

        $this->app->bind(IAffiliateRepository::class, AffiliateRepository::class);

        $this->app->bind(IAffiliateBillingRepository::class, AffiliateBillingRepository::class);

        $this->app->bind(ICampaignPrivateDealsRepository::class, CampaignPrivateDealsRepository::class);

        $this->app->bind(IMarketingGravityRepository::class, MarketingGravityRepository::class);

        $this->app->bind(IMarketingReportRepository::class, MarketingReportRepository::class);

        $this->app->bind(IMarketingBillingsRepository::class, MarketingBillingsRepository::class);

        $this->app->bind(IMarketingInvestigateRepository::class, MarketingInvestigateRepository::class);

        $this->app->bind(ITrafficEndpointSubPublisherTokensRepository::class, TrafficEndpointSubPublisherTokensRepository::class);

        $this->app->bind(ITagManagementRepository::class, TagManagementRepository::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
