import { Browser, Page } from 'playwright';
import { getBrowser, closeBrowser, getStorePage, getScreenShotPath, setTestPrefix } from '../../common/general';
import { buyStoreProduct, fillCheckoutForm, fetchOrderIdFromPage } from '../../common/store';
import faker from '@faker-js/faker';
import { serverAddress } from '../../e2e-config';
import { notReviewEmail, API_V_GOOD, ForterFlowMode, PaymentType, TextOrderSuccessMsg } from '../../common/constants';
import { CheckoutFormDataDto } from '../../common/dto/checkoutFormData.dto';
import { StoreDto } from '../../common/dto/store.dto';
import { changeApiVersion, changeForterMode} from '../../common/store-admin';
jest.setTimeout(5000000)
describe('BrainTree Not Review Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser()
        await changeApiVersion(page, API_V_GOOD)
    });
    afterEach(async () => {
        await closeBrowser()
    });

    it('Adyen No Review Deal - Verify Mode: Cron', async () => {
        setTestPrefix('adyen-approved-before')
        page = await changeForterMode(page, ForterFlowMode.Cron);
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForNavigation();
        const formData: CheckoutFormDataDto = new CheckoutFormDataDto(notReviewEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber(),
            PaymentType.Adyen)
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(7000);
        await page.screenshot({ path: getScreenShotPath('accept-deal-final-result') });
        const title = await page.locator(StoreDto.Instance.OrderSuccessMsgElmName).innerText()
        expect(title).toEqual(TextOrderSuccessMsg);
        const orderID = await fetchOrderIdFromPage(page);
        expect(orderID).not.toHaveLength(0);
        console.log(`user buy under order id (${orderID})`)
    })
})