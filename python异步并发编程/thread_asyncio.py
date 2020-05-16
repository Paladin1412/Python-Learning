# 使用多线程：在协程中集成阻赛io
import asyncio
from concurrent.futures import ThreadPoolExecutor
import socket
from urllib.parse import urlparse


def get_url():
    pass


if __name__ == '__main__':
    loop = asyncio.get_event_loop()
    executor = ThreadPoolExecutor()
    tasks = []
    for url in range(20):
        url = "http://www.{}.com".format(url)
        task = loop.run_in_executor(executor, get_url, url)
        tasks.append(task)
    loop.run_until_complete(asyncio.wait(tasks))
