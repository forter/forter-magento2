
class StoreAdminLoginDto {
    private static instance: StoreAdminLoginDto;
    public static get Instance() {
        if (!this.instance) {
            this.instance = new StoreAdminLoginDto;
        }
        return this.instance;
    }
    public get UsernameElmName() { return 'input[placeholder="user name"]'; }
    public get PasswordElmName() { return 'input[placeholder="password"]'; }
    public get LoginElmName() { return 'button.action-login'; }
}

class StoreAdminMainDto {
    private static instance: StoreAdminMainDto;
    public static get Instance() {
        if (!this.instance) {
            this.instance = new StoreAdminMainDto;
        }
        return this.instance;
    }
    public get UserTextElmName() { return '.admin-user-account-text'; }
}

class StoreAdminOrderListPageDto {
    
    private static instance: StoreAdminOrderListPageDto;
    public static get Instance() {
        if (!this.instance) {
            this.instance = new StoreAdminOrderListPageDto;
        }
        return this.instance;
    }
    public get SearchOrderElmName() { return 'input[placeholder="Search by keyword"]'; }
    public get ListHasNoDataElmName() { return 'tr.data-grid-tr-no-data >> td'}
    public get ListDataItemsElmName() { return 'tr.data-row' }
    public get SearchBillingElmName() { return 'input[name="billing_name"]' }
    public get SubmitSearchElmName() { return 'button[data-action="grid-filter-apply"]' }
}

class StoreAdminOrderPageDto {
    
    private static instance: StoreAdminOrderPageDto;
    public static get Instance() {
        if (!this.instance) {
            this.instance = new StoreAdminOrderPageDto;
        }
        return this.instance;
    }
    public get OrderTitle() { return '.page-title'; }
    public get OrderHistoryItems() { return 'ul.note-list >> .note-list-item >> .note-list-comment'}
    public get ForterTabMenu() { return 'a.tab-item-link[name="order_forter"]'}
    public get ForterTabApiStatus() { return '.invoice_item_content >> nth=0 >> div'}
    public get ForterTabDecision() { return '.invoice_item_content >> nth=1 >> div'}
    public get ForterTabReasonCode() { return '.invoice_item_content >> nth=3 >> div'}
}
class StoreAdminSettingsDto {

    private static instance: StoreAdminSettingsDto;
    public static get Instance() {
        if (!this.instance) {
            this.instance = new StoreAdminSettingsDto;
        }
        return this.instance;
    }
    public get ConfigurationStoreMenuItem() { return 'li#menu-magento-config-system-config >> a'}
    public get ForterConfigurationForterMenuItems() { return 'div.config-nav-block admin__page-nav >> .admin__page-nav-title >> strong'}
    public get CacheManagementStoreMenuItem() { return 'li#menu-magento-backend-system-cache >> a'}
    public get SelectForterFlow() { return 'select#forter_immediate_post_pre_decision_pre_post_select' }
    public get SaveForterConfig() { return 'button[title="Save Config"]' }
    public get RevalidateCacheStore() { return 'button[title="Flush Magento Cache"]' }
    public get ForterAPIVerion() { return 'input#forter_settings_api_version'; }
}

export class StoreAdminDto {
    private static instance: StoreAdminDto;
    public static get Instance() {
        if (!this.instance) {
            this.instance = new StoreAdminDto;
        }
        return this.instance;
    }
    public get Login() { return StoreAdminLoginDto.Instance; }
    public get MainAdmin() { return StoreAdminMainDto.Instance; }
    public get OrderList() { return StoreAdminOrderListPageDto.Instance; }
    public get OrderPage() { return StoreAdminOrderPageDto.Instance; }
    public get Settings() { return StoreAdminSettingsDto.Instance; }
}
