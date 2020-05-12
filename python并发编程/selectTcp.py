from selectors import DefaultSelector,EVENT_READ,EVENT_WRITE
import socket
from urllib.parse import urlparse

selector = DefaultSelector
class Fetcher:
    def get_url(self,url):
        url = urlparse(url)
        self.host=url.netloc
        self.path = url.path
        if self.path=="":
            self.path="/"
        self.client = socket.socket(socket.AF_INET,socket.SOCK_STREAM)
        self.client.setblocking(False)


