import subprocess
import time
from threading import Thread


class MonkeyTester:
    def __init__(self, times):
        self.times = times

    def monkey_test(self):
        subprocess.call("adb shell monkey -p com.xiaodianshi.tv.yst --throttle 50 -vvv 100 -s 12",shell=True)

    def back_to_index(self):
        for i in range(2):
            subprocess.call("adb shell input keyevent 4",shell=True)

    def main(self):
        for i in range(self.times):
            print("This is %d testing.."%i)
            self.back_to_index()
            self.monkey_test()
            self.back_to_index()


if __name__ == '__main__':
    mk = MonkeyTester(5)
    mk.main()
    # subprocess.check_output("adb shell cat /sdcard/ui.xml | grep '精选'", shell=True,stderr=subprocess.STDOUT)
