import aiohttp
import asyncio
import aiomysql
import re
from pyquery import PyQuery

start_url = "http://ly.home"
waiting_urls = []
seen_urls = set()
STOP = False


async def fetch(url,session):
    try:
        async with session.get(url) as resp:
            print(resp.status)
            print(await resp.text())
    except Exception as e:
        print(e)


def extract_urls(html):
    urls = []
    pq = PyQuery(html)
    for link in pq.items("a"):
        url = link.attr("href")
        if url and url.startswith("/") and url not in seen_urls:
            urls.append(start_url + url)
            waiting_urls.append(url)
    return urls


async def init_urls(url,session):
    html = await fetch(url,session)
    seen_urls.add(url)
    extract_urls(html)


async def article_handler(url, session, pool):
    # 获取详情，入库
    html = await fetch(url, session)
    seen_urls.add(url)
    extract_urls(html)
    pq = PyQuery(html)
    title = pq("title").text()
    async with pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.execute("SELECT 42;")
            insert_sql = "INSERT INTO aiomysql_test(title) value('{}')".format(title)
            await cur.execute(insert_sql)


async def consumer(pool):
    async with aiohttp.ClientSession() as session:
        while not STOP:
            if len(waiting_urls) == 0:
                await asyncio.sleep(0.5)
                continue
            url = waiting_urls.pop()
            print("start get url:{}".format(url))
            if re.match("http://ly.com/.*?", url):
                if url not in seen_urls:
                    asyncio.ensure_future(article_handler(url, session, pool))
                    await asyncio.sleep(30)
            else:
                if url not in seen_urls:
                    asyncio.ensure_future(init_urls(url,session))


async def main(loop):
    # 等待mysql连接

    pool = await aiomysql.create_pool(host="192.168.11.31", port=30001, user="michael", password="cccbbb", loop=loop,
                                      db="spider_12306", charset="utf8", autocommit=True)
    async with aiohttp.ClientSession() as session:
        html = await fetch(start_url,session)
        seen_urls.add(start_url)
        extract_urls(html)
    asyncio.ensure_future(consumer(pool))


if __name__ == '__main__':
    loop = asyncio.get_event_loop()
    asyncio.ensure_future(main(loop))
    loop.run_forever()
