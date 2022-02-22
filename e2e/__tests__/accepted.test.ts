import { Browser, Page } from 'playwright';
import { getBrowser, closeBrowser, getStorePage, getScreenShotPath, setTestPrefix } from '../common/general';
import { buyStoreProduct, CheckoutFormData, acceptEmail, fillCheckoutForm } from '../common/store';
import faker from '@faker-js/faker';
import { serverAddress } from '../e2e-config';
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
        await page.waitForTimeout(15000);
        const formData: CheckoutFormData = new CheckoutFormData(acceptEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber())
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(15000);
        await page.screenshot({ path: getScreenShotPath('accept-deal-final-result') });
        const title = await page.locator('span[data-ui-id="page-title-wrapper"]').innerText()
        expect(title).toEqual("Thank you for your purchase!");
    })
})