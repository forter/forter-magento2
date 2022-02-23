
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


}
