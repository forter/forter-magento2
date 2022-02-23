import { chromium, Browser, Page } from "playwright";
import { serverAddress } from '../e2e-config';
let browser: Browser | undefined;
let prefixTestName = 'none';
export const getBrowser = async () => {
    browser = await chromium.launch();
    console.log(`Running on server address ${serverAddress}`)
    return browser;
}
export const closeBrowser = async () => browser?.close();

export const getStorePage = async (storeAddress: string) => {
    const page: Page = await browser?.newPage() as Page;

    // page.route('**', (route, request) => {
    //     console.log(request.url());
    //     route.continue();
    // });
    await page.goto(storeAddress);
    return page;
}

export const setTestPrefix = (name: string) => prefixTestName = name;
export const getScreenShotPath = (fileName: string) => (`./e2e/screenshots/${prefixTestName}-${fileName}.png`)

export async function scrollOnElement(page: Page, selector: string) {
    await page.$eval(selector, (element) => {
        element.scrollIntoView();
    });
}