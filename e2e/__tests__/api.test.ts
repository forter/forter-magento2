import { Browser, Page } from 'playwright';
import faker from '@faker-js/faker';
import { ForterFlowMode, acceptEmail, PaymentType, TextOrderSuccessMsg, API_V_BAD, API_V_GOOD } from '../common/constants';
import { StoreAdminDto } from '../common/dto/admin/admin.dto';
import { CheckoutFormDataDto } from '../common/dto/checkoutFormData.dto';
import { StoreDto } from '../common/dto/store.dto';
import { getBrowser, closeBrowser, setTestPrefix, getStorePage, getScreenShotPath } from '../common/general';
import { buyStoreProduct, fillCheckoutForm, fetchOrderIdFromPage } from '../common/store';
import { changeApiVersion, changeForterMode, doStoreAdminLogin } from '../common/store-admin';
import { serverAddress } from '../e2e-config';
jest.setTimeout(5000000)
describe('API TESTING', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser()
    });

    afterEach(async () => {
        await closeBrowser()
    });


    it('API Change: Failed API Version Not prevent Transactions', async () => {
        setTestPrefix('api-checks')
        await changeApiVersion(page, API_V_BAD)
        page = await changeForterMode(page, ForterFlowMode.Before);
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
        await changeApiVersion(page, API_V_GOOD)
    })
})
