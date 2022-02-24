import { Locator, Page } from "playwright";
import { adminStoreUser, adminStoreUserPassword, serverAddress } from "../e2e-config";
import { ForterFlowMode } from "./constants";
import { StoreAdminDto } from "./dto/admin/admin.dto";
import { getScreenShotPath, getStorePage } from "./general";

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
    page = await goToOrderList(page, orderId);
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
export const checkOrderPage = async (page: Page, orderId: string, checkApproved: boolean) => {
    page = await goToOrderList(page, orderId);
    await page.locator(`${StoreAdminDto.Instance.OrderList.ListDataItemsElmName} >> nth=0 >> td >> nth=1`).click();
    await page.waitForLoadState('networkidle')
    await page.screenshot({ path: getScreenShotPath('orderPage') });
    const pageOrderId = await page.locator(StoreAdminDto.Instance.OrderPage.OrderTitle).textContent()
    expect(orderId).toEqual(pageOrderId?.replace('#', ''));
    const commentOrderHistoryItems = page.locator(StoreAdminDto.Instance.OrderPage.OrderHistoryItems)
    // we check the order history
    const count = await commentOrderHistoryItems.count()
    for (let i = 0; i < count; ++i) {
        const text = await commentOrderHistoryItems.nth(i).textContent();
        let regex = new RegExp(`Forter`, 'gi')
        let match = regex.test(text || '');
        if (match) {
            regex = new RegExp(`${(checkApproved) ? 'approve' : 'decline'}`, 'gi')
            match = regex.test(text || '');
            expect(match).toBeTruthy();
            break;
        }
    }
    await page.locator(StoreAdminDto.Instance.OrderPage.ForterTabMenu).click()
    await page.screenshot({ path: getScreenShotPath('orderPageForter') });
    const text = await page.locator(StoreAdminDto.Instance.OrderPage.ForterTabDecision).textContent()
    expect(text).toEqual((checkApproved) ? 'approve' : 'decline');
}

const goToOrderList = async (page: Page, orderId: string) => {
    await page.goto(`${serverAddress}/admin/sales/order/`)
    await page.waitForLoadState('networkidle')
    await page.fill(StoreAdminDto.Instance.OrderList.SearchOrderElmName, orderId);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1500);
    const countNoDataItem = await page.locator(StoreAdminDto.Instance.OrderList.ListHasNoDataElmName).count();
    expect(countNoDataItem).toBe(0)
    const locatorItems = page.locator(StoreAdminDto.Instance.OrderList.ListDataItemsElmName);
    const totalItems = await locatorItems.count();
    expect(totalItems).toBe(1)
    return page;
}

export const updateStoreForterMode = async (page: Page, mode: ForterFlowMode) => {
    await page.goto(`${serverAddress}/admin/admin/system_config/edit/section/forter#forter_immediate_post_pre_decision-link`)
    await page.waitForLoadState('networkidle')
    await page.screenshot({ path: getScreenShotPath('orderPreChangeFlowForter') });
    await page.locator(StoreAdminDto.Instance.Settings.SelectForterFlow).selectOption({ value: `${mode}` });
    await page.locator(StoreAdminDto.Instance.Settings.SaveForterConfig).click();
    await page.waitForTimeout(1500);
    await page.screenshot({ path: getScreenShotPath('orderChangeFlowForter') });
    await page.locator(StoreAdminDto.Instance.Settings.SaveForterConfig).click();
    await page.waitForTimeout(1500);
    await page.goto(`${serverAddress}/admin/admin/cache/index`)
    await page.waitForLoadState('networkidle')
    await page.screenshot({ path: getScreenShotPath('orderPreCacheForter') });
    await page.locator(StoreAdminDto.Instance.Settings.RevalidateCacheStore).click();
    await page.waitForLoadState('networkidle')
    await page.screenshot({ path: getScreenShotPath('orderPostCacheForter') });
}
export const changeForterMode = async (page: Page, mode: ForterFlowMode) => {
    page = await getStorePage(`${serverAddress}/admin`);
    page = await doStoreAdminLogin(page);
    await updateStoreForterMode(page, mode);
    return page;
}