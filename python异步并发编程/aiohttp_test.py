import aiohttp
import asyncio

from aiohttp import ClientSession


async def fetch(url):
    async with ClientSession() as session:
        async with session.get(url) as response:
            return await response.read()


async def run(loop, r):
    url = "http://localhost:8080/{}"
    tasks = []
    for i in range(r):
        task = asyncio.ensure_future(fetch(url.format(i)))
        tasks.append(task)
        responses = await asyncio.gather(*tasks)
        # you now have all response bodies in this variable
        print(responses)


def print_responses(result):
    print(result)


loop = asyncio.get_event_loop()
future = asyncio.ensure_future(run(loop, 4))
loop.run_until_complete(future)