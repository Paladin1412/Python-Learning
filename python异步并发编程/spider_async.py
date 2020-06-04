import aiohttp
import asyncio
import requests
from aiohttp import ClientSession

host = "http://api.cms.home/fy/"


def get_city_list():
    city = []
    ret = requests.get(host + "citylist").json()
    [city.append(i["city_py"]) for i in ret['data']]
    return city


def get_city(city):
    print(requests.get('http://api.cms.home/fy/current?city={}'.format(city)).json())


async def fetch(sess, url):
    async with sess.get('http://api.cms.home/fy/current?city={}'.format(url)) as response:
        await response.text()



async def async_get_city(city):
    async with aiohttp.ClientSession() as session:
        await fetch(session, city)


if __name__ == '__main__':
    import time
    now = lambda: time.time()
    city_list = get_city_list()
    [city_list.extend(city_list) for i in range(9)]
    print(len(city_list))
    loop = asyncio.get_event_loop()
    start = now()
    tasks=[]
    [tasks.append(asyncio.ensure_future(async_get_city(i))) for i in city_list]
    loop.run_until_complete(asyncio.wait(tasks))
    print((now() - start))
    # start = now()
    # for i in city_list:
    #     get_city(i)
    # print((now() - start))
# loop = asyncio.get_event_loop()
#
# loop.run_until_complete(main())
