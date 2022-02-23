import { Page } from "playwright";
import { adminStoreUser, adminStoreUserPassword, serverAddress } from "../e2e-config";
import { StoreAdminDto } from "./dto/admin/admin.dto";
import { getScreenShotPath } from "./general";

export const doStoreAdminLogin= async (page: Page) => {
    console.log(`U:${adminStoreUser} , P: ${adminStoreUserPassword}`)
    await page.fill(StoreAdminDto.Instance.Login.UsernameElmName,adminStoreUser)
    await page.fill(StoreAdminDto.Instance.Login.PasswordElmName,adminStoreUserPassword)

    await page.screenshot({ path: getScreenShotPath('pre-login') });
    await page.locator(StoreAdminDto.Instance.Login.LoginElmName).click();
    await page.waitForLoadState('networkidle')
    const userText = await page.locator(StoreAdminDto.Instance.MainAdmin.UserTextElmName).textContent();
    expect(userText).toEqual(adminStoreUser)
    return page;
}

export const checkStatusOfOrderOnOrderPage = async (page: Page, orderId: string) => {
    page.goto(`${serverAddress}/admin/sales/order/`)
    await page.waitForLoadState('networkidle')
    
}