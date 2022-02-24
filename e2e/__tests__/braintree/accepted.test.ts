import { Browser, Page } from 'playwright';
import { getBrowser, closeBrowser, getStorePage, getScreenShotPath, setTestPrefix } from '../../common/general';
import { buyStoreProduct, fillCheckoutForm, fetchOrderIdFromPage } from '../../common/store';
import faker from '@faker-js/faker';
import { serverAddress } from '../../e2e-config';
import { acceptEmail, ForterFlowMode, PaymentType, TextOrderSuccessMsg } from '../../common/constants';
import { CheckoutFormDataDto } from '../../common/dto/checkoutFormData.dto';
import { StoreDto } from '../../common/dto/store.dto';
import { checkOrderPage, checkStatusOfOrderOnOrderList, doStoreAdminLogin, updateStoreForterMode } from '../../common/store-admin';
jest.setTimeout(5000000)
describe('Testing Accepted Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser()
    });
    afterEach(async () => {
        await closeBrowser()
    });
    it('Test Approved Deal', async () => {
        setTestPrefix('braintree-geneal-approved')
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForNavigation();
        const formData: CheckoutFormDataDto = new CheckoutFormDataDto(acceptEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber(),
            PaymentType.BrainTree)
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(7000);
        await page.screenshot({ path: getScreenShotPath('accept-deal-final-result') });
        const title = await page.locator(StoreDto.Instance.OrderSuccessMsgElmName).innerText()
        expect(title).toEqual(TextOrderSuccessMsg);
        const orderID = await fetchOrderIdFromPage(page);
        expect(orderID).not.toHaveLength(0);
        console.log(`user buy under order id (${orderID})`)
        page = await getStorePage(`${serverAddress}/admin`);
        page = await doStoreAdminLogin(page);
        await checkStatusOfOrderOnOrderList(page,orderID, true)
        await checkOrderPage(page,orderID, true)
    })
    it('Test admin user actions' , async () => {
        page = await getStorePage(`${serverAddress}/admin`);
        page = await doStoreAdminLogin(page);
        await page.screenshot({ path: getScreenShotPath('dashboard') });
        await updateStoreForterMode(page, ForterFlowMode.After);
    })
})