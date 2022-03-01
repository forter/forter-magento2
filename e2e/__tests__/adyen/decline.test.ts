import { Browser, Page } from 'playwright';
import faker from '@faker-js/faker';
import { declineEmail, TextOrderErrorMsg, PaymentType, ForterFlowMode, API_V_GOOD } from '../../common/constants';
import { CheckoutFormDataDto } from '../../common/dto/checkoutFormData.dto';
import { StoreDto } from '../../common/dto/store.dto';
import { getBrowser, closeBrowser, setTestPrefix, getStorePage, getScreenShotPath } from '../../common/general';
import { buyStoreProduct, fillCheckoutForm } from '../../common/store';
import { serverAddress } from '../../e2e-config';
import { changeApiVersion, changeForterMode} from '../../common/store-admin';
jest.setTimeout(5000000)
describe('Adyen Decline Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser()
        await changeApiVersion(page, API_V_GOOD)
    });
    afterEach(async () => {
        await closeBrowser()
    });
    it('Adyen Decline Deal, Verify Mode: Cron', async () => {
        setTestPrefix('adyen-decline-cron')
        page = await changeForterMode(page, ForterFlowMode.Before);
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForTimeout(15000);
        const formData: CheckoutFormDataDto = new CheckoutFormDataDto(declineEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber(),
            PaymentType.Adyen)
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(3000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator('div[data-role="checkout-messages"]').nth(4);
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
    })
})