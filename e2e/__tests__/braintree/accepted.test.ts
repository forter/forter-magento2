import { Browser, Page } from 'playwright';
import { getBrowser, closeBrowser, getStorePage, getScreenShotPath, setTestPrefix } from '../../common/general';
import { buyStoreProduct, fillCheckoutForm } from '../../common/store';
import faker from '@faker-js/faker';
import { serverAddress } from '../../e2e-config';
import { acceptEmail, PaymentType, TextOrderSuccessMsg } from '../../common/constants';
import { CheckoutFormDataDto } from '../../common/dto/checkoutFormData.dto';
import { StoreDto } from '../../common/dto/store.dto';
jest.setTimeout(5000000)
describe('Testing Accepted Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser()
    });
    afterEach(async () => {
        await page.close();
        await closeBrowser()
    });
    it('Test Approved Deal', async () => {
        setTestPrefix('braintree-geneal-approved')
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForTimeout(5000);
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
        await page.waitForTimeout(5000);
        await page.screenshot({ path: getScreenShotPath('accept-deal-final-result') });
        const title = await page.locator(StoreDto.Instance.OrderSuccessMsgElmName).innerText()
        expect(title).toEqual(TextOrderSuccessMsg);
    })
})