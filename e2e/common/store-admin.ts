import { Locator, Page } from "playwright";
import { adminStoreUser, adminStoreUserPassword, serverAddress } from "../e2e-config";
import { TextNoDataOnTable } from "./constants";
import { StoreAdminDto } from "./dto/admin/admin.dto";
import { getScreenShotPath } from "./general";

export const doStoreAdminLogin = async (page: Page) => {
    console.log(`U:${adminStoreUser} , P: ${adminStoreUserPassword}`)
    await page.fill(StoreAdminDto.Instance.Login.UsernameElmName, adminStoreUser)
    await page.fill(StoreAdminDto.Instance.Login.PasswordElmName, adminStoreUserPassword)

    await page.screenshot({ path: getScreenShotPath('pre-login') });
    await page.locator(StoreAdminDto.Instance.Login.LoginElmName).click();
    await page.waitForLoadState('networkidle')
    const userText = await page.locator(StoreAdminDto.Instance.MainAdmin.UserTextElmName).textContent();
    expect(userText).toEqual(adminStoreUser)
    return page;
}

export const checkStatusOfOrderOnOrderList = async (page: Page, orderId: string, checkApproved: boolean) => {
    page.goto(`${serverAddress}/admin/sales/order/`)
    await page.waitForLoadState('networkidle')
    await page.fill(StoreAdminDto.Instance.OrderList.SearchOrderElmName, orderId);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1500);
    const countNoDataItem = await page.locator(StoreAdminDto.Instance.OrderList.ListHasNoDataElmName).count();
    expect(countNoDataItem).toBe(0)
    const locatorItems = page.locator(StoreAdminDto.Instance.OrderList.ListDataItemsElmName);
    const totalItems = await locatorItems.count();
    expect(totalItems).toBe(1)
    const forterItem = page.locator(`${StoreAdminDto.Instance.OrderList.ListDataItemsElmName} >> nth=0 >> td >> nth=11`)
    await page.screenshot({ path: getScreenShotPath('orderlist') });
    if (checkApproved) {
        const text = await forterItem.innerText()
        expect(text).toEqual('approve');
    }
    else {
        const text = await forterItem.innerText()
        expect(text).toEqual('decline');
    }
}
const hasOrderListHasData = async (page: Page) => {
    const text = await page.locator(StoreAdminDto.Instance.OrderList.ListHasNoDataElmName).textContent();
    expect(text).toEqual(TextNoDataOnTable)
}